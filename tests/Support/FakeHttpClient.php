<?php

declare(strict_types=1);

namespace Debi\Tests\Support;

use Debi\HttpClient\ClientInterface;
use Debi\HttpClient\Response;

/**
 * Recording, scriptable HTTP client used by every unit test that exercises
 * the request pipeline without touching the network.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $calls = [];

    /** @var list<Response|\Throwable> */
    private array $responses = [];

    public function queue(Response|\Throwable $response): self
    {
        $this->responses[] = $response;
        return $this;
    }

    public function send(string $method, string $url, array $headers, ?string $body): Response
    {
        $this->calls[] = compact('method', 'url', 'headers', 'body');

        if ($this->responses === []) {
            throw new \LogicException(
                "FakeHttpClient received an unexpected call: {$method} {$url}"
            );
        }
        $next = array_shift($this->responses);
        if ($next instanceof \Throwable) {
            throw $next;
        }
        return $next;
    }

    public function lastCall(): array
    {
        if ($this->calls === []) {
            throw new \LogicException('FakeHttpClient has not been called.');
        }
        return $this->calls[count($this->calls) - 1];
    }
}
