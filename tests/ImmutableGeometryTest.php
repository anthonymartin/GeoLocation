<?php

declare(strict_types=1);

require_once __DIR__ . '/fixtures/alternative-geometry.php';

use AnthonyMartin\GeoLocation\BoundingBox;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon;
use AnthonyMartin\GeoLocation\Interfaces\GeoPointInterface;
use AnthonyMartin\GeoLocation\Interfaces\PolygonInterface;
use AnthonyMartin\GeoLocation\Interfaces\BoundingBoxInterface;
use AnthonyMartin\GeoLocation\Interfaces\Geocoder;
use GeoLocationTests\OtherPoint;
use GeoLocationTests\OtherPolygon;
use GeoLocationTests\OtherBox;
use GeoLocationTests\OtherGeocoder;
use PHPUnit\Framework\TestCase;

final class ImmutableGeometryTest extends TestCase {
    public function testAppendingProducesANewPolygonAndRetainsTheOriginal(): void {
        $empty = new Polygon();
        $triangle = $empty->withVertex(new GeoPoint(0, 0))
            ->withVertex(new GeoPoint(0, 4))->withVertex(new GeoPoint(4, 0));
        self::assertNotSame($empty, $triangle);
        self::assertCount(0, $empty->getVertices());
        self::assertCount(3, $triangle->getVertices());
        self::assertFalse($empty->containsGeoPoint(new GeoPoint(1, 1)));
        self::assertTrue($triangle->containsGeoPoint(new GeoPoint(1, 1)));
        $vertices = $triangle->getVertices();
        $vertices[0] = new GeoPoint(60, 60);
        self::assertSame(0.0, $triangle->getVertices()[0]->getLatitude());
        self::assertTrue((new ReflectionClass(Polygon::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(Polygon::class))->isFinal());
        self::assertFalse(method_exists(Polygon::class, 'addVertex'));
    }

    public function testPolygonsSnapshotMutableAlternativePoints(): void {
        $latitude = 0.0;
        $point = $this->createStub(GeoPointInterface::class);
        $point->method('getLatitude')->willReturnCallback(static function () use (&$latitude): float { return $latitude; });
        $point->method('getLongitude')->willReturn(0.0);
        $polygon = new Polygon($point, new OtherPoint(0, 4), new OtherPoint(4, 0));
        $appended = (new Polygon())->withVertex($point);
        $latitude = 80.0;
        self::assertSame(0.0, $polygon->getVertices()[0]->getLatitude());
        self::assertSame(0.0, $appended->getVertices()[0]->getLatitude());
        self::assertTrue($polygon->containsGeoPoint(new OtherPoint(1, 1)));
    }

    public function testGeometryConsumesIndependentPointAndPolygonImplementations(): void {
        $point = new OtherPoint(1, 1);
        $polygon = OtherPolygon::fromArray([[0, 0], [0, 2], [2, 2], [2, 0]]);
        $native = new GeoPoint(1, 1);
        self::assertSame(0.0, $native->distanceTo($point, 'km'));
        self::assertEqualsWithDelta((new GeoPoint(0, 0))->distanceTo(new GeoPoint(1, 1), 'km'),
            (new GeoPoint(0, 0))->distanceTo($point, 'km'), 1e-12);
        self::assertTrue($native->inPolygon($polygon));
        self::assertTrue(Polygon::pointInPolygon($point, $polygon));
        self::assertTrue(BoundingBox::fromGeoPoint($point, 5, 'km')->contains($point));
        self::assertTrue((new BoundingBox(0, 0, 2, 2))->getPolygon()->containsGeoPoint($point));
    }

    public function testInterfaceFactoriesAndGeocoderReturnIndependentImplementations(): void {
        $resolve = static fn(Geocoder $provider): GeoPointInterface => $provider->geocode('Office');
        $point = $resolve(new OtherGeocoder());
        self::assertInstanceOf(OtherPoint::class, $point);
        /** @var class-string<BoundingBoxInterface> $boxClass */
        $boxClass = OtherBox::class;
        $box = $boxClass::fromGeoPoint($point, 5, 'km');
        self::assertInstanceOf(OtherBox::class, $box);
        self::assertInstanceOf(OtherPolygon::class, $box->getPolygon());
        self::assertTrue($point->inPolygon($box->getPolygon()));
        self::assertTrue($box->contains($point));
        /** @var class-string<PolygonInterface> $polygonClass */
        $polygonClass = OtherPolygon::class;
        self::assertInstanceOf(OtherPolygon::class, $polygonClass::fromArray([[0, 0], [0, 2], [2, 0]]));
        self::assertInstanceOf(OtherPoint::class, $box->getSouthWest());
    }
}
