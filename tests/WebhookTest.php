<?php

declare(strict_types=1);

namespace Debi\Tests;

use Debi\Exception\SignatureVerificationException;
use Debi\Resource\Event;
use Debi\Webhook;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_test_secret_value';

    #[Test]
    public function it_constructs_an_event_for_a_valid_signature(): void
    {
        $payload = '{"id":"evt_123","object":"event","type":"payment.succeeded","data":{"object":{"id":"pay_1"}}}';
        $ts = time();
        $sig = $this->sign($payload, $ts, self::SECRET);

        $event = Webhook::constructEvent($payload, "t={$ts},v1={$sig}", self::SECRET);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame('evt_123', $event->id);
        $this->assertSame('payment.succeeded', $event->type);
    }

    #[Test]
    public function it_accepts_any_of_multiple_signatures(): void
    {
        $payload = '{"object":"event","id":"evt_x","data":{}}';
        $ts = time();
        $good = $this->sign($payload, $ts, self::SECRET);
        $bogus = str_repeat('0', 64);

        $header = "t={$ts},v1={$bogus},v1={$good}";
        $event = Webhook::constructEvent($payload, $header, self::SECRET);

        $this->assertInstanceOf(Event::class, $event);
    }

    #[Test]
    public function it_tolerates_whitespace_in_header_segments(): void
    {
        $payload = '{"object":"event","id":"x","data":{}}';
        $ts = time();
        $sig = $this->sign($payload, $ts, self::SECRET);

        $event = Webhook::constructEvent($payload, "  t={$ts} , v1={$sig} ", self::SECRET);

        $this->assertSame('x', $event->id);
    }

    #[Test]
    public function it_rejects_mismatched_signatures(): void
    {
        $payload = '{"object":"event","id":"x","data":{}}';
        $ts = time();

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('matched');

        Webhook::constructEvent($payload, "t={$ts},v1=" . str_repeat('a', 64), self::SECRET);
    }

    #[Test]
    public function it_rejects_when_payload_was_tampered(): void
    {
        $payload = '{"object":"event","id":"x","data":{}}';
        $ts = time();
        $sig = $this->sign($payload, $ts, self::SECRET);

        $this->expectException(SignatureVerificationException::class);

        Webhook::constructEvent($payload . ' ', "t={$ts},v1={$sig}", self::SECRET);
    }

    #[Test]
    public function it_rejects_a_stale_timestamp(): void
    {
        $payload = '{"object":"event","id":"x","data":{}}';
        $ts = time() - 600;
        $sig = $this->sign($payload, $ts, self::SECRET);

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('tolerance');

        Webhook::constructEvent($payload, "t={$ts},v1={$sig}", self::SECRET, 300);
    }

    #[Test]
    public function it_rejects_a_future_timestamp_outside_tolerance(): void
    {
        $payload = '{"object":"event","id":"x","data":{}}';
        $ts = time() + 600;
        $sig = $this->sign($payload, $ts, self::SECRET);

        $this->expectException(SignatureVerificationException::class);

        Webhook::constructEvent($payload, "t={$ts},v1={$sig}", self::SECRET, 300);
    }

    #[Test]
    public function it_skips_timestamp_check_when_tolerance_is_zero(): void
    {
        $payload = '{"object":"event","id":"x","data":{}}';
        $ts = time() - 86_400;
        $sig = $this->sign($payload, $ts, self::SECRET);

        $event = Webhook::constructEvent($payload, "t={$ts},v1={$sig}", self::SECRET, 0);
        $this->assertSame('x', $event->id);
    }

    #[Test]
    public function it_rejects_an_empty_header(): void
    {
        $this->expectException(SignatureVerificationException::class);
        Webhook::verifySignature('payload', '', self::SECRET);
    }

    #[Test]
    public function it_rejects_a_header_without_timestamp(): void
    {
        $this->expectException(SignatureVerificationException::class);
        Webhook::verifySignature('payload', 'v1=' . str_repeat('a', 64), self::SECRET);
    }

    #[Test]
    public function it_rejects_a_header_without_any_signature(): void
    {
        $this->expectException(SignatureVerificationException::class);
        Webhook::verifySignature('payload', 't=' . time(), self::SECRET);
    }

    #[Test]
    public function it_rejects_a_non_numeric_timestamp(): void
    {
        $this->expectException(SignatureVerificationException::class);
        Webhook::verifySignature('payload', 't=NOTANUMBER,v1=' . str_repeat('a', 64), self::SECRET);
    }

    #[Test]
    public function it_rejects_an_empty_secret(): void
    {
        $this->expectException(SignatureVerificationException::class);
        Webhook::verifySignature('payload', 't=1,v1=' . str_repeat('a', 64), '');
    }

    #[Test]
    public function it_rejects_invalid_json_after_signature_verification(): void
    {
        $payload = 'not-json';
        $ts = time();
        $sig = $this->sign($payload, $ts, self::SECRET);

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('JSON');

        Webhook::constructEvent($payload, "t={$ts},v1={$sig}", self::SECRET);
    }

    private function sign(string $payload, int $ts, string $secret): string
    {
        return hash_hmac('sha256', $ts . '.' . $payload, $secret);
    }
}
