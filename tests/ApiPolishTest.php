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

readonly class LabelledReviewPoint extends GeoPoint {
    private string $label;
    public string $uninitialized;

    public function __construct(float $lat, float $lon, string $label, public object $metadata) {
        parent::__construct($lat, $lon);
        $this->label = $label;
        $metadata->constructorCalls++;
    }

    public function label(): string { return $this->label; }
}

readonly class ChildReviewPoint extends LabelledReviewPoint {
    private string $label;
    protected float $degLat;

    public function __construct(float $lat, float $lon, string $label, object $metadata) {
        parent::__construct($lat, $lon, $label, $metadata);
        $this->label = 'child label';
    }

    public function childLabel(): string { return $this->label; }
}

final class ApiPolishTest extends TestCase {
    public function testReplacementsPreserveSubclassFieldsWithoutCallingItsConstructor(): void {
        $metadata = (object) ['constructorCalls' => 0];
        $point = new ChildReviewPoint(40, -74, 'Office', $metadata);
        foreach ([$point->withLatitude(41), $point->withLongitude(-73)] as $copy) {
            self::assertInstanceOf(ChildReviewPoint::class, $copy);
            self::assertNotSame($point, $copy);
            self::assertSame('Office', $copy->label());
            self::assertSame('child label', $copy->childLabel());
            self::assertSame($metadata, $copy->metadata);
            self::assertFalse((new ReflectionProperty($copy, 'uninitialized'))->isInitialized($copy));
        }
        self::assertSame(1, $metadata->constructorCalls);
        self::assertSame(40.0, $point->getLatitude());
        self::assertSame(-74.0, $point->getLongitude());
        self::assertSame(41.0, $point->withLatitude(41)->getLatitude());
        self::assertSame(-73.0, $point->withLongitude(-73)->getLongitude());
    }

    public function testReplacementsPreserveUnchangedRadiansExactly(): void {
        $point = new GeoPoint(0.123456789012345, -1.23456789012345, true);
        self::assertSame($point->getLongitude(true), $point->withLatitude(30)->getLongitude(true));
        self::assertSame($point->getLatitude(true), $point->withLongitude(30)->getLatitude(true));
        self::assertSame(0.123456789012345, $point->withLatitude(0.123456789012345, true)->getLatitude(true));
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
