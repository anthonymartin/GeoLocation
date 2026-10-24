<?php

declare(strict_types=1);

// Execute the documentation's actual code with local geocoding fixtures.
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/fixtures/geocoding-transport.php';

use AnthonyMartin\GeoLocation\GeocodingCurlFixture;

$readme = file_get_contents(__DIR__ . '/../README.md');
preg_match_all('/```php\s*\n(.*?)\n```/s', $readme, $matches);
if ($matches[1] === []) {
    throw new RuntimeException('No PHP examples found in README.');
}
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
    foreach ($matches[1] as $index => $example) {
        GeocodingCurlFixture::reset();
        $example = str_replace("__DIR__ . '/vendor/autoload.php'",
            var_export(__DIR__ . '/../vendor/autoload.php', true), $example);
        $path = tempnam(sys_get_temp_dir(), 'geolocation-readme-');
        try {
            file_put_contents($path, $example);
            ob_start();
            try {
                (static function (string $file): void { require $file; })($path);
            } catch (Throwable $error) {
                throw new RuntimeException('README example ' . ($index + 1) . ' failed.', 0, $error);
            } finally {
                ob_end_clean();
            }
        } finally {
            unlink($path);
        }
    }
} finally {
    restore_error_handler();
}
printf("%d README examples passed on PHP %s.\n", count($matches[1]), PHP_VERSION);
