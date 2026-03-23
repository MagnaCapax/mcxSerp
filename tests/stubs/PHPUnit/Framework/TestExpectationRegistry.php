<?php

declare(strict_types=1);

namespace PHPUnit\Framework;

final class TestExpectationRegistry
{
    private static ?string $expectedException = null;

    public static function setExpectedException(string $exception): void
    {
        self::$expectedException = $exception;
    }

    public static function consumeExpectedException(): ?string
    {
        $expected = self::$expectedException;
        self::$expectedException = null;

        return $expected;
    }
}
