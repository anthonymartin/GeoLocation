<?php

declare(strict_types=1);

use AnthonyMartin\GeoLocation\BoundingBox;
use AnthonyMartin\GeoLocation\Earth;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\GoogleGeocoder;
use AnthonyMartin\GeoLocation\Interfaces\BoundingBoxInterface;
use AnthonyMartin\GeoLocation\Interfaces\Geocoder;
use AnthonyMartin\GeoLocation\Interfaces\PolygonInterface;
use AnthonyMartin\GeoLocation\Polygon;
use PHPUnit\Framework\TestCase;

final class ApiPolishTest extends TestCase {
    public function testReplacementsRetainCoordinateValuesWithinFloatingPointPrecision(): void {
        $point = new GeoPoint(0.123456789012345, -1.23456789012345, true);
        self::assertSame($point->getLongitude(), $point->withLatitude(30)->getLongitude());
        self::assertEqualsWithDelta($point->getLongitude(true), $point->withLatitude(30)->getLongitude(true), 1e-15);
        self::assertSame($point->getLatitude(), $point->withLongitude(30)->getLatitude());
        self::assertEqualsWithDelta($point->getLatitude(true), $point->withLongitude(30)->getLatitude(true), 1e-15);
        self::assertEqualsWithDelta(0.123456789012345, $point->withLatitude(0.123456789012345, true)->getLatitude(true), 1e-15);
    }

    public function testInterfaceAndImplementationNamedArgumentsAgree(): void {
        foreach ([GeoPoint::class, BoundingBox::class, Polygon::class, GoogleGeocoder::class] as $class) {
            $reflection = new ReflectionClass($class);
            foreach ($reflection->getInterfaces() as $interface) {
                foreach ($interface->getMethods() as $method) {
                    $names = static fn(ReflectionMethod $m): array => array_map(
                        static fn(ReflectionParameter $p): string => $p->getName(), $m->getParameters()
                    );
                    self::assertSame($names($method), $names($reflection->getMethod($method->getName())));
                }
            }
        }
        $point = new GeoPoint(1, 1);
        $box = new BoundingBox(0, 0, 2, 2);
        $checkBox = static fn(BoundingBoxInterface $box): bool => $box->contains(point: $point);
        $checkPolygon = static fn(PolygonInterface $polygon): bool => $polygon->containsGeoPoint(point: $point);
        self::assertTrue($checkBox($box));
        self::assertTrue($checkPolygon($box->getPolygon()));
    }

    public function testEarthBoundsAreInitializedInRadians(): void {
        self::assertSame(-M_PI_2, Earth::$MIN_LAT);
        self::assertSame(M_PI_2, Earth::$MAX_LAT);
        self::assertSame(-M_PI, Earth::$MIN_LON);
        self::assertSame(M_PI, Earth::$MAX_LON);
        self::assertSame(Earth::getMINLAT(), Earth::$MIN_LAT);
        self::assertSame(Earth::getMAXLON(), Earth::$MAX_LON);
    }

    public function testGeocoderCanBeReplacedWithAProviderOrTestDouble(): void {
        $point = new GeoPoint(40.5, -74.4);
        $provider = new class($point) implements Geocoder {
            public function __construct(private GeoPoint $point) {}
            public function geocode(string $address): GeoPoint { return $this->point; }
        };
        $resolve = static fn(Geocoder $geocoder): GeoPoint => $geocoder->geocode('Office');
        self::assertSame($point, $resolve($provider));
        $mock = $this->createMock(Geocoder::class);
        $mock->expects(self::once())->method('geocode')->with('Office')->willReturn($point);
        self::assertSame($point, $resolve($mock));
        self::assertInstanceOf(Geocoder::class, new GoogleGeocoder('test-key'));
    }

    public function testGeocoderRejectsAnUnexpectedTransportResult(): void {
        require_once __DIR__ . '/fixtures/geocoding-transport.php';
        \AnthonyMartin\GeoLocation\GeocodingCurlFixture::reset();
        \AnthonyMartin\GeoLocation\GeocodingCurlFixture::$body = true;
        $this->expectException(\AnthonyMartin\GeoLocation\Exceptions\UnexpectedResponseException::class);
        $this->expectExceptionMessage('did not return a response body');
        (new GoogleGeocoder('test-key'))->geocode('Office');
    }

}
