<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Infrastructure\Dns;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Infrastructure\Dns\DnsResolution;
use Tragwerk\Infrastructure\Dns\DnsResolver;

use function array_map;
use function assert;
use function chr;
use function explode;
use function gethostbyname;
use function pack;
use function str_repeat;
use function strlen;

final class DnsResolverTest extends TestCase
{
    private const int ID = 0x1234;

    #[Test]
    public function resolvedReturnsIpFromARecord(): void
    {
        $response = $this->header(self::ID, ancount: 1)
            . $this->question('example.com')
            . $this->aRecord('93.184.216.34');

        $result = (new DnsResolver())->parseResponse($response, self::ID);

        self::assertSame(DnsResolution::RESOLVED, $result->status);
        self::assertSame('93.184.216.34', $result->ip);
    }

    #[Test]
    public function followsCnameChainToTheFinalARecord(): void
    {
        $response = $this->header(self::ID, ancount: 2)
            . $this->question('blog.example.com')
            . $this->cnameRecord("\x03www\xc0\x0c") // CNAME → compressed pointer, must be skipped
            . $this->aRecord('167.233.203.169');

        $result = (new DnsResolver())->parseResponse($response, self::ID);

        self::assertSame(DnsResolution::RESOLVED, $result->status);
        self::assertSame('167.233.203.169', $result->ip);
    }

    #[Test]
    public function validAnswerWithoutAnARecordIsNotFound(): void
    {
        $response = $this->header(self::ID, ancount: 0) . $this->question('nope.example.com');

        $result = (new DnsResolver())->parseResponse($response, self::ID);

        self::assertSame(DnsResolution::NOT_FOUND, $result->status);
        self::assertNull($result->ip);
    }

    #[Test]
    public function answerWithOnlyANonARecordIsNotFound(): void
    {
        // A single AAAA record (type 28, 16-byte rdata) → no usable A record.
        $aaaa     = "\xc0\x0c" . pack('nnNn', 28, 1, 300, 16) . str_repeat("\x00", 16);
        $response = $this->header(self::ID, ancount: 1) . $this->question('v6.example.com') . $aaaa;

        $result = (new DnsResolver())->parseResponse($response, self::ID);

        self::assertSame(DnsResolution::NOT_FOUND, $result->status);
    }

    #[Test]
    public function transactionIdMismatchIsUnreachable(): void
    {
        $response = $this->header(0x9999, ancount: 1)
            . $this->question('example.com')
            . $this->aRecord('93.184.216.34');

        $result = (new DnsResolver())->parseResponse($response, self::ID);

        self::assertSame(DnsResolution::UNREACHABLE, $result->status);
    }

    #[Test]
    public function malformedResponseIsUnreachable(): void
    {
        $result = (new DnsResolver())->parseResponse("\x00\x01", self::ID);

        self::assertSame(DnsResolution::UNREACHABLE, $result->status);
    }

    #[Test]
    public function fallsBackToSystemResolverWhenNameserverUnreachable(): void
    {
        $host = 'one.one.one.one'; // Cloudflare's own hostname, stable A record

        if (gethostbyname($host) === $host) {
            self::markTestSkipped('System resolver cannot reach DNS in this environment.');
        }

        // TEST-NET-1 (192.0.2.0/24) is unroutable → the direct UDP query times out and the
        // resolver must fall back to the system resolver instead of reporting UNREACHABLE.
        $result = (new DnsResolver('192.0.2.1'))->resolve($host);

        self::assertSame(DnsResolution::RESOLVED, $result->status);
        self::assertNotNull($result->ip);
    }

    private function header(int $id, int $ancount): string
    {
        return pack('nnnnnn', $id, 0x8180, 1, $ancount, 0, 0);
    }

    private function question(string $host): string
    {
        $qname = '';
        foreach (explode('.', $host) as $label) {
            $len = strlen($label);
            assert($len <= 0xFF);
            $qname .= chr($len) . $label;
        }

        return $qname . "\x00" . pack('nn', 1, 1); // QTYPE=A, QCLASS=IN
    }

    private function aRecord(string $ip): string
    {
        $octets = array_map('intval', explode('.', $ip));

        return "\xc0\x0c" . pack('nnNn', 1, 1, 300, 4) . pack('C4', ...$octets);
    }

    private function cnameRecord(string $rdata): string
    {
        return "\xc0\x0c" . pack('nnNn', 5, 1, 300, strlen($rdata)) . $rdata;
    }
}
