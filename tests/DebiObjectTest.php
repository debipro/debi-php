<?php

declare(strict_types=1);

namespace Debi\Tests;

use Debi\DebiObject;
use Debi\Resource\Customer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the array-backed value object that every API response inherits
 * from. The contract is small but it is touched by every single call the SDK
 * makes, so the round-trips and operator behavior are pinned explicitly.
 */
final class DebiObjectTest extends TestCase
{
    #[Test]
    public function property_and_array_access_agree(): void
    {
        $o = DebiObject::constructFrom(['id' => 'x', 'email' => 'a@b.com']);

        $this->assertSame('x', $o->id);
        $this->assertSame('x', $o['id']);
        $this->assertTrue(isset($o->id));
        $this->assertTrue(isset($o['id']));
    }

    #[Test]
    public function unknown_keys_return_null_without_warnings(): void
    {
        // The point of the array-backed model is forward compatibility: when
        // the API adds a field the SDK has not yet documented, user code
        // reading it should get null, not a PHP notice.
        $o = DebiObject::constructFrom([]);

        $this->assertNull($o->never_set);
        $this->assertNull($o['never_set']);
        $this->assertFalse(isset($o->never_set));
        $this->assertFalse(isset($o['never_set']));
    }

    #[Test]
    public function setting_via_property_or_array_access_persists(): void
    {
        $o = DebiObject::constructFrom(['id' => 'x']);
        $o->name = 'Ana';
        $o['email'] = 'a@b.com';

        $this->assertSame('Ana', $o->name);
        $this->assertSame('a@b.com', $o['email']);
    }

    #[Test]
    public function count_reports_top_level_field_count(): void
    {
        $o = DebiObject::constructFrom(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertCount(3, $o);
    }

    #[Test]
    public function to_array_recurses_through_nested_debi_objects(): void
    {
        $o = DebiObject::constructFrom([
            'id' => 'x',
            'customer' => ['object' => 'customer', 'id' => 'CS1'],
            'tags' => ['vip', 'priority'],
            'nested' => ['list' => [['object' => 'customer', 'id' => 'CS2']]],
        ]);

        $array = $o->toArray();

        $this->assertSame('x', $array['id']);
        $this->assertIsArray($array['customer']);
        $this->assertSame('CS1', $array['customer']['id']);
        $this->assertSame(['vip', 'priority'], $array['tags']);
        $this->assertSame('CS2', $array['nested']['list'][0]['id']);
        $this->assertArrayNotHasKey('values', $array, 'Internal storage key must not leak through toArray().');
    }

    #[Test]
    public function json_serialize_matches_to_array(): void
    {
        $o = Customer::constructFrom([
            'id' => 'CSjRZ5JqjAw0',
            'object' => 'customer',
            'email' => 'a@b.com',
        ]);

        $encoded = json_encode($o, JSON_THROW_ON_ERROR);
        $this->assertSame($o->toArray(), json_decode($encoded, true, flags: JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function refresh_from_replaces_not_merges(): void
    {
        // After refreshFrom() the object must reflect exactly the new state
        // the server returned. A merge would leave stale fields visible to
        // user code (e.g. a `cancelled_at` from a prior call surviving a
        // subsequent retrieve()).
        $o = DebiObject::constructFrom(['id' => 'x', 'stale' => 'leftover']);
        $o->refreshFrom(['id' => 'x', 'fresh' => 'new']);

        $this->assertSame('new', $o->fresh);
        $this->assertNull($o->stale);
    }

    #[Test]
    public function unset_removes_the_field(): void
    {
        $o = DebiObject::constructFrom(['id' => 'x', 'email' => 'a@b.com']);
        unset($o->email);

        $this->assertFalse(isset($o->email));
        $this->assertArrayNotHasKey('email', $o->toArray());
    }
}
