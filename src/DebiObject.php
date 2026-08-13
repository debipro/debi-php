<?php

declare(strict_types=1);

namespace Debi;

/**
 * Base for every object returned by the Debi API.
 *
 * Intentionally array-backed rather than declaring a typed property for every
 * field, so that the SDK does not need a release every time the API adds a
 * new field. Use PHPDoc `@property` on subclasses to document the shape.
 *
 * Property reads are the strict path and complain about fields the response did
 * not carry; array access is the lenient one and accepts any key. See
 * {@see __get()} for which to reach for.
 *
 * @implements \ArrayAccess<string, mixed>
 *
 * @phpstan-consistent-constructor
 */
class DebiObject implements \ArrayAccess, \JsonSerializable, \Countable
{
    /** @var array<string, mixed> */
    protected array $values = [];

    /** @var array<string, true> */
    protected array $unsavedValues = [];

    /**
     * Subclasses must keep this signature unchanged. {@see constructFrom()} is
     * the only supported way to create a populated instance.
     *
     * @final
     */
    public function __construct() {}

    /**
     * @param array<string,mixed> $values
     */
    public static function constructFrom(array $values): static
    {
        $instance = new static();
        $instance->refreshFrom($values);
        return $instance;
    }

    /**
     * @param array<string,mixed> $values
     */
    public function refreshFrom(array $values): void
    {
        $this->values = [];
        foreach ($values as $k => $v) {
            $this->values[(string) $k] = Util\Util::convertToObject($v);
        }
        $this->unsavedValues = [];
    }

    /**
     * Reading a field the response did not carry raises an `E_USER_WARNING` and
     * evaluates to null. A field that was misspelled or renamed is otherwise
     * impossible to tell apart from one the API legitimately returned as null,
     * which turns a typo into a silent null flowing through the caller's logic.
     * A field the API did send as null reads as null without complaint.
     *
     * Three ways to read a field without tripping the warning, for when absence
     * is a legitimate answer rather than a mistake — probing for something a
     * newer API version returns but the installed SDK does not document yet,
     * for instance:
     *
     *     $object['new_field']           // array access
     *     $object->new_field ?? $default // `??` consults __isset() first
     *     isset($object->new_field)
     *
     * Note for applications that promote warnings to exceptions (Laravel's
     * default error handler does): this read will throw there rather than
     * return null. That is the intended severity, but it makes the escape
     * hatches above load-bearing.
     */
    public function __get(string $key): mixed
    {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        trigger_error(
            sprintf(
                '%s has no field `%s`. Check the @property list on that class for '
                . 'the field names the API returns, or read it as $object[\'%s\'] '
                . 'if you expect a field this SDK version does not document yet.',
                static::class,
                $key,
                $key,
            ),
            E_USER_WARNING,
        );

        return null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
        $this->unsavedValues[$key] = true;
    }

    public function __isset(string $key): bool
    {
        return isset($this->values[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->values[$key], $this->unsavedValues[$key]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->values[(string) $offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->values[(string) $offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->__set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->__unset((string) $offset);
    }

    public function count(): int
    {
        return count($this->values);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->values as $k => $v) {
            $result[$k] = $this->valueToArray($v);
        }
        return $result;
    }

    private function valueToArray(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value->toArray();
        }
        if (is_array($value)) {
            return array_map($this->valueToArray(...), $value);
        }
        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string,mixed>
     */
    public function __debugInfo(): array
    {
        return $this->values;
    }
}
