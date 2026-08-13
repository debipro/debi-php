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
    public function reading_an_unknown_property_warns_and_returns_null(): void
    {
        // A field the response never carried is almost always a typo or a
        // stale assumption about the API's shape. Returning a bare null lets
        // it travel silently into the caller's logic, so the read is loud.
        $o = DebiObject::constructFrom([]);

        $value = null;
        $warnings = $this->captureUserWarnings(static function () use ($o, &$value): void {
            $value = $o->never_set;
        });

        $this->assertNull($value);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('never_set', $warnings[0]);
        $this->assertStringContainsString(DebiObject::class, $warnings[0]);
    }

    #[Test]
    public function the_warning_names_the_concrete_resource_class(): void
    {
        // "Debi\DebiObject has no field x" would send the reader hunting
        // through the base class; the @property list they need is on Customer.
        $customer = Customer::constructFrom(['id' => 'CS1', 'object' => 'customer']);

        $warnings = $this->captureUserWarnings(static function () use ($customer): void {
            $customer->emial;
        });

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString(Customer::class, $warnings[0]);
    }

    #[Test]
    public function array_access_and_isset_stay_silent_for_unknown_keys(): void
    {
        // Forward compatibility: reading a field the SDK has not documented
        // yet must remain possible without tripping the warning above.
        $o = DebiObject::constructFrom([]);

        $this->assertNoUserWarning(function () use ($o): void {
            $this->assertNull($o['never_set']);
            $this->assertFalse(isset($o->never_set));
            $this->assertFalse(isset($o['never_set']));
        });
    }

    #[Test]
    public function the_null_coalescing_operator_stays_silent_for_unknown_properties(): void
    {
        // `??` consults __isset() and short-circuits before __get() ever runs.
        // This is the escape hatch callers reach for most, and applications
        // that promote warnings to exceptions depend on it not throwing.
        $o = DebiObject::constructFrom([]);

        $this->assertNoUserWarning(function () use ($o): void {
            $this->assertSame('fallback', $o->never_set ?? 'fallback');
        });
    }

    #[Test]
    public function a_field_the_api_returned_as_null_does_not_warn(): void
    {
        // `deleted_at: null` is a field the API sent, not a missing one.
        $o = DebiObject::constructFrom(['deleted_at' => null]);

        $this->assertNoUserWarning(function () use ($o): void {
            $this->assertNull($o->deleted_at);
        });
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
        $this->assertNull($o['stale']);
    }

    #[Test]
    public function unset_removes_the_field(): void
    {
        $o = DebiObject::constructFrom(['id' => 'x', 'email' => 'a@b.com']);
        unset($o->email);

        $this->assertFalse(isset($o->email));
        $this->assertArrayNotHasKey('email', $o->toArray());
    }

    /**
     * Run $fn with E_USER_WARNING intercepted and return the messages raised.
     *
     * @return list<string>
     */
    private function captureUserWarnings(callable $fn): array
    {
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        }, E_USER_WARNING);

        try {
            $fn();
        } finally {
            restore_error_handler();
        }

        return $warnings;
    }

    private function assertNoUserWarning(callable $fn): void
    {
        $warnings = $this->captureUserWarnings($fn);
        $this->assertSame([], $warnings, 'Expected no E_USER_WARNING to be raised.');
    }
}
