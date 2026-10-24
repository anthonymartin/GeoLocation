<?php

require_once __DIR__ . '/runtime-bootstrap.php';
require_once __DIR__ . '/compatibility-cases.php';

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$count = 0;
foreach (backwardCompatibilityCases() as $name => $case) {
    $case(function ($expected, $actual, $message) use (&$count, $name) {
        if ($expected !== $actual) {
            throw new RuntimeException($name . ': ' . $message);
        }
        $count++;
    });
}
restore_error_handler();
echo 'Backward compatibility: ' . $count . ' assertions passed on PHP ' . PHP_VERSION . ".\n";
