<?php

require_once __DIR__ . '/compatibility-cases.php';

use PHPUnit\Framework\TestCase;

class BackwardCompatibilityTest extends TestCase {
    private function checkContract($name) {
        $cases = backwardCompatibilityCases();
        set_error_handler(function ($severity, $message, $file, $line) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $cases[$name](function ($expected, $actual, $message) {
                $this->assertSame($expected, $actual, $message);
            });
        } catch (\Throwable $error) {
            restore_error_handler();
            throw $error;
        }
        restore_error_handler();
    }

    public function testLegacyGeocoderReturnsRawResponsesOrNull() { $this->checkContract('legacy_geocoding'); }
    public function testModernTransportFailuresKeepTheOldCatchType() { $this->checkContract('transport_catch'); }
    public function testIndexedCornersMatchVersion201() { $this->checkContract('corner_indexes'); }
    public function testExistingSubclassOverridesLoadAndWork() { $this->checkContract('subclass_overrides'); }
    public function testIncompletePolygonsReturnFalseWithoutWarnings() { $this->checkContract('incomplete_polygons'); }
    public function testGettersRetainOriginalInputTypes() { $this->checkContract('coordinate_types'); }
    public function testComposerAllowsGeometryWithoutGeocodingExtensions() { $this->checkContract('optional_extensions'); }

    public function testGeometryAndGeocoderFallbacksWithoutExtensionFunctions() {
        foreach (array('curl_init', 'json_decode', 'curl_init,json_decode') as $disabled) {
            $command = escapeshellarg(PHP_BINARY) . ' -d ' . escapeshellarg('disable_functions=' . $disabled) . ' ' .
                escapeshellarg(__DIR__ . '/without-geocoding-extensions.php');
            $output = array();
            exec($command . ' 2>&1', $output, $status);
            $this->assertSame(0, $status, implode("\n", $output));
        }
    }
}
