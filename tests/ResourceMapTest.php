<?php

declare(strict_types=1);

namespace Debi\Tests;

use Debi\ApiResource;
use Debi\Resource;
use Debi\Util\Util;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pins the contract between every concrete {@see ApiResource} subclass and
 * the discriminator table in {@see Util}. If the two ever drift (a new
 * resource forgets to register, or a typo creeps into an `OBJECT_NAME`),
 * API responses silently degrade to a generic `DebiObject` and downstream
 * `instanceof` checks fail in user code without any visible cause.
 */
final class ResourceMapTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<ApiResource>}>
     */
    public static function resourceClassProvider(): iterable
    {
        yield 'Customer'         => [Resource\Customer::class];
        yield 'Payment'          => [Resource\Payment::class];
        yield 'Subscription'     => [Resource\Subscription::class];
        yield 'Mandate'          => [Resource\Mandate::class];
        yield 'PaymentMethod'    => [Resource\PaymentMethod::class];
        yield 'Refund'           => [Resource\Refund::class];
        yield 'Session'          => [Resource\Session::class];
        yield 'Link'             => [Resource\Link::class];
        yield 'Event'            => [Resource\Event::class];
        yield 'Export'           => [Resource\Export::class];
        yield 'Import'           => [Resource\Import::class];
        yield 'Gateway'          => [Resource\Gateway::class];
        yield 'WebhookEndpoint'  => [Resource\WebhookEndpoint::class];
    }

    /**
     * @param class-string<ApiResource> $class
     */
    #[Test]
    #[DataProvider('resourceClassProvider')]
    public function resource_class_round_trips_through_the_discriminator_map(string $class): void
    {
        $name = $class::OBJECT_NAME;
        $this->assertNotSame('', $name, "{$class} must declare a non-empty OBJECT_NAME.");

        $hydrated = Util::convertToObject(['object' => $name, 'id' => 'x']);

        $this->assertInstanceOf(
            $class,
            $hydrated,
            "Util::RESOURCE_MAP is missing an entry for '{$name}' or it is wired to the wrong class.",
        );
    }

    #[Test]
    public function unknown_discriminator_falls_back_to_generic_debi_object_without_throwing(): void
    {
        // Forward-compatibility guarantee: when the server starts returning
        // a resource type the SDK has not yet learned about, the SDK must
        // keep functioning rather than crash on hydration.
        $hydrated = Util::convertToObject([
            'object' => 'definitely_not_a_real_resource_name',
            'id' => 'x',
        ]);

        $this->assertInstanceOf(\Debi\DebiObject::class, $hydrated);
        $this->assertNotInstanceOf(ApiResource::class, $hydrated);
    }
}
