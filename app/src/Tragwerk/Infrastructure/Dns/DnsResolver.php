<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Dns;

use function assert;
use function chr;
use function explode;
use function fclose;
use function filter_var;
use function fread;
use function fsockopen;
use function fwrite;
use function gethostbyname;
use function is_array;
use function ord;
use function pack;
use function random_int;
use function stream_set_timeout;
use function strlen;
use function substr;
use function unpack;

use const FILTER_FLAG_IPV4;
use const FILTER_VALIDATE_IP;

readonly class DnsResolver
{
    private const int PORT    = 53;
    private const int TIMEOUT = 3;

    public function __construct(private string $nameserver = '1.1.1.1')
    {
    }

    /**
     * Resolves a hostname to an IPv4 address.
     *
     * Queries the configured public resolver directly over UDP first, bypassing the OS
     * DNS cache. CNAME chains are resolved by the recursive nameserver — the response
     * includes the final A record. When that resolver cannot be reached (e.g. outbound
     * port 53 is blocked), falls back to the system resolver so a correct domain is not
     * wrongly rejected. The three-valued result lets callers tell "domain has no A
     * record" apart from "we could not reach any resolver".
     */
    public function resolve(string $host): DnsResult
    {
        $viaResolver = $this->queryOverUdp($host);
        if ($viaResolver->status === DnsResolution::RESOLVED) {
            return $viaResolver;
        }

        $systemIp = $this->querySystem($host);
        if ($systemIp !== null) {
            return DnsResult::resolved($systemIp);
        }

        // No IP from either path. Trust an authoritative "no A record" answer from the UDP
        // resolver; otherwise no resolver was reachable at all → unreachable.
        return $viaResolver->status === DnsResolution::NOT_FOUND
            ? DnsResult::notFound()
            : DnsResult::unreachable();
    }

    /**
     * Convenience wrapper returning the resolved IPv4 address, or null when the host does
     * not resolve or no resolver could be reached.
     */
    public function toIpv4(string $host): string|null
    {
        return $this->resolve($host)->ip;
    }

    /**
     * Parses a raw DNS response for the first A record.
     *
     * Public for deterministic, network-free testing of the wire-format handling.
     * Returns UNREACHABLE for responses that are not a valid answer to our query
     * (transaction-id mismatch / malformed), NOT_FOUND for a valid answer without an
     * A record, and RESOLVED with the IPv4 address otherwise.
     */
    public function parseResponse(string $response, int $expectedId): DnsResult
    {
        // A DNS message header is 12 bytes; anything shorter is not a valid answer.
        if (strlen($response) < 12) {
            return DnsResult::unreachable();
        }

        /** @var array{id: int, flags: int, qdcount: int, ancount: int}|false $header */
        $header = unpack('nid/nflags/nqdcount/nancount', substr($response, 0, 8));
        if (! is_array($header) || $header['id'] !== $expectedId) {
            return DnsResult::unreachable();
        }

        $qdcount = $header['qdcount'];
        $ancount = $header['ancount'];

        // Skip the question section
        $offset = 12;
        for ($i = 0; $i < $qdcount; $i++) {
            $offset  = $this->skipName($response, $offset);
            $offset += 4; // QTYPE + QCLASS
        }

        // Scan answer records — the recursive resolver includes the final A record even
        // when the chain goes through intermediate CNAMEs.
        for ($i = 0; $i < $ancount; $i++) {
            $offset = $this->skipName($response, $offset);

            if ($offset + 10 > strlen($response)) {
                break;
            }

            /** @var array{type: int, class: int, ttl: int, rdlength: int}|false $rr */
            $rr = unpack('ntype/nclass/Nttl/nrdlength', substr($response, $offset, 10));
            if (! is_array($rr)) {
                break;
            }

            $type     = $rr['type'];
            $rdlength = $rr['rdlength'];
            $offset  += 10;

            if ($type === 1 && $rdlength === 4 && $offset + 4 <= strlen($response)) {
                return DnsResult::resolved(
                    ord($response[$offset]) . '.'
                    . ord($response[$offset + 1]) . '.'
                    . ord($response[$offset + 2]) . '.'
                    . ord($response[$offset + 3]),
                );
            }

            $offset += $rdlength;
        }

        // Valid response from the resolver, but no usable A record for this host.
        return DnsResult::notFound();
    }

    private function queryOverUdp(string $host): DnsResult
    {
        $socket = fsockopen('udp://' . $this->nameserver, self::PORT, $errno, $errstr, self::TIMEOUT);
        if ($socket === false) {
            return DnsResult::unreachable();
        }

        stream_set_timeout($socket, self::TIMEOUT);

        $id = random_int(0, 0xFFFF);

        if (fwrite($socket, $this->buildAQuery($id, $host)) === false) {
            fclose($socket);

            return DnsResult::unreachable();
        }

        $response = fread($socket, 512);
        fclose($socket);

        if ($response === false || strlen($response) < 12) {
            return DnsResult::unreachable();
        }

        return $this->parseResponse($response, $id);
    }

    /**
     * Resolves via the OS resolver (/etc/resolv.conf). Used as a fallback when the direct
     * UDP query cannot reach its nameserver — gethostbyname only ever yields IPv4 and
     * returns the input unchanged on failure.
     */
    private function querySystem(string $host): string|null
    {
        $ip = gethostbyname($host);

        if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $ip;
        }

        return null;
    }

    private function buildAQuery(int $id, string $host): string
    {
        $header = pack(
            'nnnnnn',
            $id,    // Transaction ID
            0x0100, // Flags: standard query, recursion desired
            1,      // QDCOUNT: 1 question
            0,      // ANCOUNT
            0,      // NSCOUNT
            0,      // ARCOUNT
        );

        $qname = '';
        foreach (explode('.', $host) as $label) {
            $len = strlen($label);
            assert($len <= 0xFF); // DNS labels are max 63 chars per RFC 1035
            $qname .= chr($len) . $label;
        }

        $qname .= "\x00";

        return $header . $qname . pack('nn', 1, 1); // QTYPE=A, QCLASS=IN
    }

    private function skipName(string $data, int $offset): int
    {
        $len = strlen($data);
        while ($offset < $len) {
            $byte = ord($data[$offset]);
            if ($byte === 0) {
                return $offset + 1;
            }

            // DNS name compression pointer (top 2 bits set) — always 2 bytes total
            if (($byte & 0xC0) === 0xC0) {
                return $offset + 2;
            }

            $offset += 1 + $byte;
        }

        return $offset;
    }
}
