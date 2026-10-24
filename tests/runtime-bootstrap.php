<?php

// Load the source directly for checks without PHPUnit or optional extensions.
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
