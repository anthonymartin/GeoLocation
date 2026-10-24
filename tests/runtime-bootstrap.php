<?php

// Keep dependency-free runtime checks usable on the PHP 5.4 support floor.
// PHPUnit's Composer dependencies intentionally require a newer PHP version.
spl_autoload_register(function ($class) {
    $prefix = 'AnthonyMartin\\GeoLocation\\';
    if (strpos($class, $prefix) === 0) {
        $path = __DIR__ . '/../src/AnthonyMartin/GeoLocation/' .
            str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});
