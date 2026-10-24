<?php

declare(strict_types=1);

use AnthonyMartin\GeoLocation\{BoundingBox, Circle, Earth, GeoPoint, Polygon};
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;
use AnthonyMartin\GeoLocation\Interfaces\Region;
use PHPUnit\Framework\TestCase;

final class RegionTest extends TestCase {
    public function testRegionsExposeTheirMembershipAndEnvelope(): void {
        $box = new BoundingBox(-1, -1, 1, 1);
        foreach ([$box, $box->getPolygon(), new Circle(new GeoPoint(0, 0), 100, 'km')] as $region) {
            self::assertInstanceOf(Region::class, $region);
            self::assertTrue($region->contains(new GeoPoint(0, 0)));
            self::assertFalse($region->contains(new GeoPoint(2, 2)));
            self::assertTrue($region->boundingBox()->contains(new GeoPoint(0, 0)));
        }
        self::assertSame($box, $box->boundingBox());
        self::assertTrue($box->contains(new GeoPoint(-1, -1)));
        self::assertSame(-1.0, $box->getPolygon()->boundingBox()->getMinLatitude());
        self::assertSame(1.0, $box->getPolygon()->boundingBox()->getMaxLongitude());
    }

    public function testCircleBoundaryAndBoxCorners(): void {
        $center = new GeoPoint(0, 0);
        $edge = new GeoPoint(1, 0);
        $circle = $center->circle($center->distanceTo($edge, 'km'), 'km');
        self::assertTrue($circle->contains($edge));
        self::assertFalse($circle->contains(new GeoPoint(1.000001, 0)));
        foreach ($circle->boundingBox()->getVertices() as $corner) {
            self::assertFalse($circle->contains($corner));
        }
        foreach (range(-9, 9) as $i) {
            $point = new GeoPoint($i / 10, 0.1);
            self::assertTrue($circle->contains($point));
            self::assertTrue($circle->boundingBox()->contains($point));
        }
    }

    public function testCircleWrapsAtAntimeridianAndIncludesPoles(): void {
        $circle = new Circle(new GeoPoint(0, 179.9), 50, 'km');
        self::assertTrue($circle->contains(new GeoPoint(0, -179.9)));
        self::assertTrue($circle->boundingBox()->contains(new GeoPoint(0, -179.9)));
        self::assertFalse($circle->contains(new GeoPoint(0, 170)));
        $polar = new Circle(new GeoPoint(89.9, 0), 30, 'km');
        self::assertTrue($polar->contains(new GeoPoint(90, 180)));
        self::assertTrue($polar->boundingBox()->contains(new GeoPoint(90, 180)));
        $world = new Circle(new GeoPoint(80, 170), 2 * M_PI * Earth::RADIUS_KM, 'km');
        self::assertTrue($world->contains(new GeoPoint(-80, -10)));
        self::assertTrue($world->boundingBox()->contains(new GeoPoint(-80, -10)));
    }

    public function testZeroRadiusAndInvalidRadii(): void {
        $point = new GeoPoint(1, 2);
        self::assertTrue($point->circle(0, 'mi')->contains($point));
        self::assertFalse($point->circle(0, 'mi')->contains(new GeoPoint(1, 2.000001)));
        foreach ([-1.0, NAN, INF] as $radius) {
            try { new Circle($point, $radius, 'km'); self::fail('Invalid radius accepted'); }
            catch (InvalidArgumentException) { self::assertTrue(true); }
        }
    }

    public function testCircleSnapshotsAlternativePoints(): void {
        $point = $this->createStub(\AnthonyMartin\GeoLocation\Interfaces\GeoPointInterface::class);
        $lat = 1.0;
        $point->method('getLatitude')->willReturnCallback(static function () use (&$lat) { return $lat; });
        $point->method('getLongitude')->willReturn(2.0);
        $circle = new Circle($point, 0, 'km');
        $lat = 50.0;
        self::assertTrue($circle->contains(new GeoPoint(1, 2)));
        self::assertSame(1.0, $circle->getCenter()->getLatitude());
    }

    public function testEmptyPolygonHasNoEnvelope(): void {
        $this->expectException(InvalidArgumentException::class);
        (new Polygon())->boundingBox();
    }
}
