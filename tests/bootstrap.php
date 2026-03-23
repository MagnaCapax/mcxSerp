<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$requireAll = static function (string $directory): void {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $files[] = $file->getPathname();
    }

    sort($files);

    foreach ($files as $file) {
        require_once $file;
    }
};

$requireAll($root . '/tests/stubs');

spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'MagnaCapax\\Serp\\Tests\\' => $root . '/tests/',
        'MagnaCapax\\Serp\\' => $root . '/src/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relativePath = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        $file = $baseDir . $relativePath;

        if (is_file($file)) {
            require_once $file;
        }
    }
});

$requireAll($root . '/tests/Http');
$requireAll($root . '/tests/Model');
$requireAll($root . '/tests/Provider');
