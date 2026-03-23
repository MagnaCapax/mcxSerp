<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$classes = array_filter(
    get_declared_classes(),
    static fn (string $class): bool => is_subclass_of($class, PHPUnit\Framework\TestCase::class)
);

$failures = 0;
$tests = 0;

foreach ($classes as $class) {
    $reflection = new ReflectionClass($class);

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (!str_starts_with($method->getName(), 'test')) {
            continue;
        }

        $instance = $reflection->newInstance();
        $expectedException = null;

        try {
            if ($reflection->hasMethod('setUp')) {
                $setup = $reflection->getMethod('setUp');
                $setup->setAccessible(true);
                $setup->invoke($instance);
            }

            $method->invoke($instance);
            $expectedException = PHPUnit\Framework\TestExpectationRegistry::consumeExpectedException();
            if ($expectedException !== null) {
                throw new PHPUnit\Framework\AssertionFailedError('Expected exception ' . $expectedException . ' was not thrown.');
            }
            $tests++;
            echo '.';
        } catch (Throwable $exception) {
            $expectedException = PHPUnit\Framework\TestExpectationRegistry::consumeExpectedException();
            if ($expectedException !== null && $exception instanceof $expectedException) {
                $tests++;
                echo '.';
                continue;
            }

            $tests++;
            $failures++;
            echo 'F';
            fwrite(STDERR, PHP_EOL . $class . '::' . $method->getName() . PHP_EOL . $exception . PHP_EOL);
        }
    }
}

echo PHP_EOL . sprintf('Tests: %d, Failures: %d', $tests, $failures) . PHP_EOL;

exit($failures === 0 ? 0 : 1);
