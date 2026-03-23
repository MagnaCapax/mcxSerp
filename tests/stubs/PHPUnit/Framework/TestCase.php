<?php

declare(strict_types=1);

namespace PHPUnit\Framework;

abstract class TestCase
{
    protected function setUp(): void
    {
    }

    public static function assertTrue(bool $condition, string $message = ''): void
    {
        if (!$condition) {
            throw new AssertionFailedError($message !== '' ? $message : 'Failed asserting that condition is true.');
        }
    }

    public static function assertFalse(bool $condition, string $message = ''): void
    {
        if ($condition) {
            throw new AssertionFailedError($message !== '' ? $message : 'Failed asserting that condition is false.');
        }
    }

    public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new AssertionFailedError($message !== '' ? $message : 'Failed asserting that two values are identical.');
        }
    }

    public static function assertNull(mixed $actual, string $message = ''): void
    {
        if ($actual !== null) {
            throw new AssertionFailedError($message !== '' ? $message : 'Failed asserting that value is null.');
        }
    }

    public static function assertCount(int $expectedCount, Countable|iterable $haystack, string $message = ''): void
    {
        $count = is_countable($haystack) ? count($haystack) : iterator_count((function () use ($haystack) {
            yield from $haystack;
        })());

        if ($count !== $expectedCount) {
            throw new AssertionFailedError($message !== '' ? $message : 'Failed asserting count.');
        }
    }

    public static function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new AssertionFailedError($message !== '' ? $message : 'Failed asserting that string contains substring.');
        }
    }

    public static function assertInstanceOf(string $expected, mixed $actual, string $message = ''): void
    {
        if (!$actual instanceof $expected) {
            throw new AssertionFailedError($message !== '' ? $message : 'Failed asserting instance type.');
        }
    }

    public static function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            throw new AssertionFailedError($message !== '' ? $message : 'Failed asserting that array has key.');
        }
    }

    public static function assertJson(string $actual, string $message = ''): void
    {
        json_decode($actual, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AssertionFailedError($message !== '' ? $message : 'Failed asserting valid JSON.');
        }
    }

    public static function fail(string $message): never
    {
        throw new AssertionFailedError($message);
    }

    public static function expectException(string $exception): void
    {
        TestExpectationRegistry::setExpectedException($exception);
    }
}
