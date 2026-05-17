<?php

declare(strict_types=1);

namespace Debi\Tests;

use Debi\DebiObject;
use Debi\Resource\Customer;
use Debi\Resource\Payment;
use Debi\Util\Util;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the small set of helpers in {@see Util} that bridge raw decoded
 * JSON and the typed object graph the SDK exposes to users.
 */
final class UtilTest extends TestCase
{
    #[Test]
    public function convert_to_object_hydrates_known_discriminator(): void
    {
        $result = Util::convertToObject([
            'object' => 'customer',
            'id' => 'CSjRZ5JqjAw0',
            'email' => 'a@b.com',
        ]);

        $this->assertInstanceOf(Customer::class, $result);
        $this->assertSame('a@b.com', $result->email);
    }

    #[Test]
    public function convert_to_object_returns_generic_for_unknown_discriminator(): void
    {
        $result = Util::convertToObject([
            'object' => 'something_new_the_sdk_does_not_know_yet',
            'id' => 'x',
        ]);

        $this->assertInstanceOf(DebiObject::class, $result);
        $this->assertNotInstanceOf(Customer::class, $result);
    }

    #[Test]
    public function convert_to_object_returns_generic_when_object_field_missing(): void
    {
        $result = Util::convertToObject(['foo' => 'bar']);
        $this->assertInstanceOf(DebiObject::class, $result);
    }

    #[Test]
    public function convert_to_object_recurses_into_nested_resources(): void
    {
        $result = Util::convertToObject([
            'object' => 'payment',
            'id' => 'PY1',
            'customer' => ['object' => 'customer', 'id' => 'CS1', 'email' => 'a@b.com'],
        ]);

        $this->assertInstanceOf(Payment::class, $result);
        $this->assertInstanceOf(Customer::class, $result->customer);
        $this->assertSame('a@b.com', $result->customer->email);
    }

    #[Test]
    public function convert_to_object_preserves_sequential_arrays_as_arrays(): void
    {
        // Sequential arrays are JSON arrays (lists), not JSON objects. They
        // must NOT be wrapped in a DebiObject — that would break iteration
        // and json_encode round-tripping.
        $result = Util::convertToObject([1, 2, 3]);

        $this->assertIsArray($result);
        $this->assertSame([1, 2, 3], $result);
    }

    #[Test]
    public function convert_to_object_passes_scalars_through(): void
    {
        $this->assertSame('x', Util::convertToObject('x'));
        $this->assertSame(7, Util::convertToObject(7));
        $this->assertNull(Util::convertToObject(null));
        $this->assertTrue(Util::convertToObject(true));
    }

    #[Test]
    public function objects_to_ids_replaces_resource_objects_with_their_id(): void
    {
        // This is the "you can pass either an id or the object you just
        // received from the API" convenience. Pin it: silent failure here
        // would mean either sending the wrong payload to the server or
        // raising a JSON-encoding error mid-flight.
        $customer = Customer::constructFrom(['id' => 'CSjRZ5JqjAw0', 'object' => 'customer']);

        $out = Util::objectsToIds([
            'customer' => $customer,
            'amount' => 100,
        ]);

        $this->assertSame(['customer' => 'CSjRZ5JqjAw0', 'amount' => 100], $out);
    }

    #[Test]
    public function objects_to_ids_recurses_into_nested_arrays(): void
    {
        $customer = Customer::constructFrom(['id' => 'CS1', 'object' => 'customer']);

        $out = Util::objectsToIds([
            'filters' => ['customer' => $customer, 'status' => 'active'],
            'limit' => 10,
        ]);

        $this->assertSame([
            'filters' => ['customer' => 'CS1', 'status' => 'active'],
            'limit' => 10,
        ], $out);
    }

    #[Test]
    public function objects_to_ids_leaves_objects_without_id_untouched(): void
    {
        // A user-built DebiObject without an `id` is left in place — encoding
        // will follow whatever its `jsonSerialize` produces (a plain hash),
        // which is the desired behavior for inline structured params.
        $payload = DebiObject::constructFrom(['email' => 'a@b.com']);

        $out = Util::objectsToIds(['nested' => $payload]);

        $this->assertSame($payload, $out['nested']);
    }
}
