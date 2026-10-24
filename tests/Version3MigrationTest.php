<?php

declare(strict_types=1);

use AnthonyMartin\GeoLocation\BoundingBox;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\GoogleGeocoder;
use AnthonyMartin\GeoLocation\Polygon;
use AnthonyMartin\GeoLocation\Exceptions\ExceptionInterface;
use PHPUnit\Framework\TestCase;

readonly class Version3Point extends GeoPoint {
    public function distanceTo(GeoPoint $geopoint, mixed $unit_of_measurement): float {
        return parent::distanceTo($geopoint, $unit_of_measurement);
    }

    public function tryToMutateLatitude(): void { $this->degLat = 20.0; }
}

class Version3Box extends BoundingBox {
    public function __construct(GeoPoint $center, float $km) {
        $box = $center->boundingBox($km, 'km');
        parent::__construct($box->getMinLatitude(), $box->getMinLongitude(), $box->getMaxLatitude(), $box->getMaxLongitude());
    }
}

class Version3MigrationTest extends TestCase {
    public function testCoordinateGettersAlwaysReturnFloats(): void {
        foreach ([1, 1.5, '1.50'] as $value) {
            foreach ([false, true] as $radians) {
                $point = new GeoPoint($value, $value, $radians);
                $this->assertSame((float) $value, $point->getLatitude($radians));
                $this->assertSame((float) $value, $point->getLongitude($radians));
                foreach (['getDegLat', 'getDegLon', 'getRadLat', 'getRadLon'] as $getter) {
                    $this->assertIsFloat($point->$getter());
                }
                $box = new BoundingBox($value, $value, $value, $value, $radians);
                $expected = $radians ? rad2deg((float) $value) : (float) $value;
                foreach (['getMinLatitude', 'getMinLongitude', 'getMaxLatitude', 'getMaxLongitude'] as $getter) {
                    $this->assertSame($expected, $box->$getter());
                }
                foreach ($box->getVertices() as $corner) {
                    $this->assertSame($expected, $corner->getLatitude());
                    $this->assertSame($expected, $corner->getLongitude());
                }
            }
        }
    }

    public function testWithMethodsLeaveTheOriginalAndOtherCoordinateUnchanged(): void {
        $original = new GeoPoint(40.5187154, -74.4120953);
        $north = $original->withLatitude('41.5');
        $east = $original->withLongitude('-73.5');
        $this->assertNotSame($original, $north);
        $this->assertNotSame($original, $east);
        $this->assertSame(40.5187154, $original->getLatitude());
        $this->assertSame(-74.4120953, $original->getLongitude());
        $this->assertSame(-74.4120953, $north->getLongitude());
        $this->assertSame(40.5187154, $east->getLatitude());
        $this->assertSame(41.5, $north->getLatitude());
        $this->assertSame(-73.5, $east->getLongitude());
        $moved = $north->withLongitude(-73.5);
        $this->assertSame(0.0, $moved->distanceTo(new GeoPoint(41.5, -73.5), 'km'));
        $this->assertSame(deg2rad(41.5), $moved->getLatitude(true));
        $this->assertTrue($moved->boundingBox(0, 'km')->contains($moved));
    }

    public function testWithMethodsAcceptRadiansAndInclusiveBounds(): void {
        $point = new GeoPoint(12.3456789012345, -74.4120953);
        foreach ([-M_PI / 2, 0.5, M_PI / 2] as $latitude) {
            $changed = $point->withLatitude($latitude, true);
            $this->assertSame(rad2deg($latitude), $changed->getLatitude());
            $this->assertEqualsWithDelta($latitude, $changed->getLatitude(true), 1e-15);
            $this->assertSame($point->getLongitude(), $changed->getLongitude());
        }
        foreach ([-M_PI, -1.0, M_PI] as $longitude) {
            $changed = $point->withLongitude($longitude, true);
            $this->assertSame(rad2deg($longitude), $changed->getLongitude());
            $this->assertEqualsWithDelta($longitude, $changed->getLongitude(true), 1e-15);
            $this->assertSame($point->getLatitude(), $changed->getLatitude());
        }
    }

    public function testInvalidReplacementCoordinatesThrowWithoutChangingTheOriginal(): void {
        $point = new GeoPoint(10, 20);
        foreach (['withLatitude' => 90, 'withLongitude' => 180] as $method => $limit) {
            foreach ([false, true] as $radians) {
                $maximum = $radians ? deg2rad($limit) : $limit;
                foreach ([null, true, false, '', 'north', [], new stdClass(), NAN, INF, -INF, '1e309', -$maximum - 1, $maximum + 1] as $invalid) {
                    try {
                        $point->$method($invalid, $radians);
                        $this->fail('Invalid replacement was accepted.');
                    } catch (ExceptionInterface $exception) {
                        $this->assertNotSame('', $exception->getMessage());
                        $this->assertSame(10.0, $point->getLatitude());
                        $this->assertSame(20.0, $point->getLongitude());
                    }
                }
            }
        }
    }

    public function testReadonlyPointCannotBeMutatedByASubclass(): void {
        $point = new Version3Point(10, 20);
        $this->assertTrue((new ReflectionClass(GeoPoint::class))->isReadOnly());
        $this->assertInstanceOf(Version3Point::class, $point->withLatitude(11));
        $this->expectException(Error::class);
        $point->tryToMutateLatitude();
    }

    public function testRemovedApisCannotBeUsedAccidentally(): void {
        $this->assertFalse(class_exists('AnthonyMartin\\GeoLocation\\GeoLocation'));
        $this->assertFalse(interface_exists('AnthonyMartin\\GeoLocation\\Interfaces\\GeoLocationInterface'));
        foreach (['setDegLat', 'setDegLon', 'setRadLat', 'setRadLon', 'fromAddress'] as $method) {
            $this->assertFalse(method_exists(GeoPoint::class, $method));
        }
        $this->assertFalse(method_exists(GoogleGeocoder::class, 'request'));
    }

    public function testInterfacesNeverConstrainConstructors(): void {
        foreach (['GeoPointInterface', 'BoundingBoxInterface', 'Polygon'] as $name) {
            $interface = new ReflectionClass('AnthonyMartin\\GeoLocation\\Interfaces\\' . $name);
            $this->assertFalse($interface->hasMethod('__construct'));
            foreach ($interface->getMethods() as $method) {
                $this->assertTrue($method->hasReturnType());
            }
        }
        $point = new Version3Point(40, -74);
        $this->assertSame(0.0, $point->distanceTo($point, 'km'));
        $this->assertTrue((new Version3Box($point, 1))->contains($point));
    }

    public function testGeometryAndMissingExtensionErrorsInIsolatedProcesses(): void {
        foreach (['curl_init', 'json_decode', 'curl_init,json_decode'] as $disabled) {
            $command = escapeshellarg(PHP_BINARY) . ' -d ' . escapeshellarg('disable_functions=' . $disabled) . ' ' .
                escapeshellarg(__DIR__ . '/without-geocoding-extensions.php');
            $output = [];
            exec($command . ' 2>&1', $output, $status);
            $this->assertSame(0, $status, implode("\n", $output));
        }
    }
}
