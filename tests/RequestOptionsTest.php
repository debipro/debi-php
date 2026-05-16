<?php

declare(strict_types=1);

namespace Debi\Tests;

use Debi\RequestOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestOptionsTest extends TestCase
{
    #[Test]
    public function null_yields_empty_options(): void
    {
        $opts = RequestOptions::parse(null);
        $this->assertNull($opts->apiKey);
        $this->assertNull($opts->idempotencyKey);
        $this->assertSame([], $opts->headers);
    }

    #[Test]
    public function array_form_is_converted(): void
    {
        $opts = RequestOptions::parse([
            'api_key' => 'sk_x',
            'idempotency_key' => 'k',
            'api_version' => '2025-10-02',
            'headers' => ['X-Trace' => 'abc'],
        ]);
        $this->assertSame('sk_x', $opts->apiKey);
        $this->assertSame('k', $opts->idempotencyKey);
        $this->assertSame('2025-10-02', $opts->apiVersion);
        $this->assertSame(['X-Trace' => 'abc'], $opts->headers);
    }

    #[Test]
    public function existing_options_pass_through_unchanged(): void
    {
        $original = new RequestOptions(apiKey: 'sk_x');
        $this->assertSame($original, RequestOptions::parse($original));
    }

    #[Test]
    public function it_rejects_non_string_option_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        /** @phpstan-ignore-next-line — intentional misuse */
        RequestOptions::parse(['api_key' => 123]);
    }

    #[Test]
    public function it_rejects_non_array_headers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        /** @phpstan-ignore-next-line — intentional misuse */
        RequestOptions::parse(['headers' => 'X-Trace: abc']);
    }
}
