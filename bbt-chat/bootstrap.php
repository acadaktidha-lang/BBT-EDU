<?php
declare(strict_types=1);

/** Minimal PSR-4 autoloader for the BBTChat namespace, used by the CLI tools and by the plugin. */
spl_autoload_register(static function (string $class): void {
    $prefix = 'BBTChat\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file     = __DIR__ . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
