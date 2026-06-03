<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Dns;

use function assert;
use function chr;
use function explode;
use function fclose;
use function fread;
use function fsockopen;
use function fwrite;
use function is_array;
use function ord;
use function pack;
use function random_int;
use function stream_set_timeout;
use function strlen;
use function substr;
use function unpack;

readonly class DnsResolver
{
    private const string NAMESERVER = '1.1.1.1';
    private const int PORT          = 53;
    private const int TIMEOUT       = 3;

    /**
     * Resolves a hostname to an IPv4 address by querying a public DNS resolver
     * directly over UDP, bypassing the OS DNS cache. CNAME chains are resolved
     * by the recursive nameserver — the response includes the final A record.
     *
     * Returns null when the domain cannot be resolved.
     */
    public function toIpv4(string $host): string|null
    {
        $socket = fsockopen('udp://' . self::NAMESERVER, self::PORT, $errno, $errstr, self::TIMEOUT);
        if ($socket === false) {
            return null;
        }

        stream_set_timeout($socket, self::TIMEOUT);

        $id = random_int(0, 0xFFFF);

        if (fwrite($socket, $this->buildAQuery($id, $host)) === false) {
            fclose($socket);

            return null;
        }

        $response = fread($socket, 512);
        fclose($socket);

        if ($response === false || strlen($response) < 12) {
            return null;
        }

        return $this->parseFirstARecord($response, $id);
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

    private function parseFirstARecord(string $response, int $expectedId): string|null
    {
        /** @var array{id: int, flags: int, qdcount: int, ancount: int}|false $header */
        $header = unpack('nid/nflags/nqdcount/nancount', substr($response, 0, 8));
        if (! is_array($header)) {
            return null;
        }

        $id      = $header['id'];
        $qdcount = $header['qdcount'];
        $ancount = $header['ancount'];

        if ($id !== $expectedId || $ancount === 0) {
            return null;
        }

        // Skip the question section
        $offset = 12;
        for ($i = 0; $i < $qdcount; $i++) {
            $offset  = $this->skipName($response, $offset);
            $offset += 4; // QTYPE + QCLASS
        }

        // Scan answer records — 1.1.1.1 is recursive, so even when there are
        // intermediate CNAMEs in the chain, the final A record is included here.
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
                return ord($response[$offset]) . '.'
                    . ord($response[$offset + 1]) . '.'
                    . ord($response[$offset + 2]) . '.'
                    . ord($response[$offset + 3]);
            }

            $offset += $rdlength;
        }

        return null;
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
