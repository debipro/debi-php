<?php

declare(strict_types=1);

namespace Debi\Tests\Support;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as Psr18ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Scriptable PSR-18 client used by {@see \Debi\Tests\HttpClient\DefaultClientTest}.
 *
 * Queue either a {@see ResponseInterface} (to be returned) or a
 * {@see ClientExceptionInterface} (to be thrown) for each expected attempt;
 * every call is recorded so tests can assert on the request shape and the
 * number of attempts the retry policy made.
 */
final class FakePsr18Client implements Psr18ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|ClientExceptionInterface> */
    private array $scripted = [];

    public function queue(ResponseInterface|ClientExceptionInterface $next): self
    {
        $this->scripted[] = $next;
        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        if ($this->scripted === []) {
            throw new \LogicException(
                "FakePsr18Client received an unexpected call: {$request->getMethod()} {$request->getUri()}"
            );
        }
        $next = array_shift($this->scripted);
        if ($next instanceof ClientExceptionInterface) {
            throw $next;
        }
        return $next;
    }

    public function attemptCount(): int
    {
        return count($this->requests);
    }
}
