<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use AnthonyMartin\GeoLocation\BoundingBox;
use AnthonyMartin\GeoLocation\Earth;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon;
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;
use AnthonyMartin\GeoLocation\Exceptions\InvalidBoundingBoxCoordinatesException;

class GeometryRegressionTest extends TestCase
{
    public function testBoundingBoxPerimeterPreservesCorrectPolygonConstruction()
    {
        $box = new BoundingBox(10, 30, 20, 40);
        $expected = array(array(10, 30), array(10, 40), array(20, 40), array(20, 30));
        foreach ($box->getPerimeter() as $index => $vertex) {
            $this->assertEquals($expected[$index][0], $vertex->getLatitude());
            $this->assertEquals($expected[$index][1], $vertex->getLongitude());
        }
        $this->assertSame($box->getVertices(), $box->getGeoPoints());
        $this->assertEquals($box->getPolygon()->getVertices(), $box->toPolygon()->getVertices());

        // The old SW, SE, NW, NE order formed a bow tie and missed these points.
        foreach (array(array(12, 32), array(12, 38), array(18, 32), array(18, 38)) as $coordinates) {
            $this->assertTrue($box->toPolygon()->containsGeoPoint(new GeoPoint($coordinates[0], $coordinates[1])));
        }
        $this->assertFalse($box->toPolygon()->containsGeoPoint(new GeoPoint(21, 35)));
    }

    public function testLegacyCornerIndexesAndNamedGettersAgreeForOrdinaryAndWrappedBoxes()
    {
        foreach (array(array(10, 30, 20, 40), array(-10, 170, 10, -170)) as $bounds) {
            $box = new BoundingBox($bounds[0], $bounds[1], $bounds[2], $bounds[3]);
            $expected = array(array($bounds[0], $bounds[1]), array($bounds[0], $bounds[3]),
                array($bounds[2], $bounds[1]), array($bounds[2], $bounds[3]));
            $names = array('getSouthWest', 'getSouthEast', 'getNorthWest', 'getNorthEast');
            $vertices = $box->getVertices();
            foreach ($names as $index => $name) {
                $corner = $box->$name();
                $this->assertSame($vertices[$index], $corner);
                $this->assertEquals($expected[$index], array($corner->getLatitude(), $corner->getLongitude()));
                $this->assertTrue($box->contains($corner));
            }
            $this->assertSame($vertices, $box->getGeoPoints());
            $this->assertSame(array($vertices[0], $vertices[1], $vertices[3], $vertices[2]), $box->getPerimeter());
            $this->assertSame($box->getPerimeter(), $box->getPolygon()->getVertices());
        }
    }

    public function testZeroCoordinatesAndNumericStringsAreAccepted()
    {
        foreach (array(array(0, -1, 1, 1), array(-1, 0, 1, 1),
                array(-1, -1, 0, 1), array(-1, -1, 1, 0), array(0, 0, 0, 0)) as $coordinates) {
            $box = new BoundingBox($coordinates[0], $coordinates[1], $coordinates[2], $coordinates[3]);
            $this->assertTrue($box->contains(new GeoPoint(0, 0)));
        }
        $box = new BoundingBox('0', '0', '10', '20');
        $this->assertTrue($box->contains(new GeoPoint(5, 10)));
    }

    public function testRadiansBoundingBoxHasConsistentCoordinates()
    {
        $box = new BoundingBox(0, 0, M_PI / 4, M_PI / 2, true);
        $this->assertEquals(45, $box->getMaxLatitude());
        $this->assertEquals(90, $box->getMaxLongitude());
        $this->assertTrue($box->contains(new GeoPoint(45, 90)));
        $this->assertFalse($box->contains(new GeoPoint(46, 90)));
        $vertices = $box->getVertices();
        $this->assertEquals(M_PI / 2, $vertices[3]->getLongitude(true));
        $this->assertEquals(0, $vertices[2]->getLongitude(true));
    }

    public function testMalformedBoundingBoxCoordinatesThrowLibraryExceptions()
    {
        foreach (array(null, false, true, '', 'north', array(), new \stdClass(), INF, -INF, NAN, '1e9999') as $invalid) {
            for ($index = 0; $index < 4; $index++) {
                $coordinates = array(-10, -20, 10, 20);
                $coordinates[$index] = $invalid;
                try {
                    new BoundingBox($coordinates[0], $coordinates[1], $coordinates[2], $coordinates[3]);
                    $this->fail('Invalid bounding box coordinates were accepted.');
                } catch (InvalidBoundingBoxCoordinatesException $exception) {
                    $this->assertNotEmpty($exception->getMessage());
                }
            }
        }
    }

    public function testInvertedLatitudeBoundsAreRejected()
    {
        $this->expectException('AnthonyMartin\GeoLocation\Exceptions\InvalidBoundingBoxCoordinatesException');
        new BoundingBox(20, 30, 10, 40);
    }

    public function testOutOfRangeBoundingBoxCoordinatesAreRejected()
    {
        $this->expectException('AnthonyMartin\GeoLocation\Exceptions\OutOfBoundsException');
        new BoundingBox(-91, -180, 90, 180);
    }

    public function testBoundingBoxContainsAllEdgesAndCorners()
    {
        $box = new BoundingBox(-10, -20, 10, 20);
        foreach (array(-10, 0, 10) as $latitude) {
            foreach (array(-20, 0, 20) as $longitude) {
                $this->assertTrue($box->contains(new GeoPoint($latitude, $longitude)));
            }
        }
        foreach (array(array(-11, 0), array(11, 0), array(0, -21), array(0, 21)) as $coordinates) {
            $this->assertFalse($box->contains(new GeoPoint($coordinates[0], $coordinates[1])));
        }
    }

    public function testAntimeridianBoundingBoxContainsBothSidesAndItsEdges()
    {
        $box = new BoundingBox(-10, 170, 10, -170);
        foreach (array(-10, 0, 10) as $latitude) {
            foreach (array(170, 175, 180, -180, -175, -170) as $longitude) {
                $this->assertTrue($box->contains(new GeoPoint($latitude, $longitude)));
            }
        }
        foreach (array(0, 169, -169) as $longitude) {
            $this->assertFalse($box->contains(new GeoPoint(0, $longitude)));
        }
        $this->assertFalse($box->contains(new GeoPoint(11, 180)));
    }

    public function testBothAntimeridianRepresentationsMatchBoundingBoxEndpoints()
    {
        foreach (array(array(-1, 170, 1, 180), array(-1, -180, 1, -170),
                array(-1, 180, 1, 180), array(-1, -180, 1, -180), array(-1, 180, 1, -180)) as $coordinates) {
            $box = new BoundingBox($coordinates[0], $coordinates[1], $coordinates[2], $coordinates[3]);
            $this->assertTrue($box->contains(new GeoPoint(0, 180)));
            $this->assertTrue($box->contains(new GeoPoint(0, -180)));
            $this->assertFalse($box->contains(new GeoPoint(0, 0)));
        }
    }

    public function testGeneratedAntimeridianBoxContainsItsCenter()
    {
        foreach (array(179.9, -179.9) as $longitude) {
            $center = new GeoPoint(0, $longitude);
            $box = $center->boundingBox(100, 'km');
            $this->assertGreaterThan($box->getMaxLongitude(), $box->getMinLongitude());
            $this->assertTrue($box->contains($center));
            $this->assertTrue($box->contains(new GeoPoint(0, 180)));
            $this->assertTrue($box->contains(new GeoPoint(0, -180)));
            $this->assertFalse($box->contains(new GeoPoint(0, 0)));
        }
    }

    public function testZeroDistancePreservesCoordinatesAndContainsTheCenter()
    {
        foreach (array(array(0, 0), array(40.5187154, -74.4120953),
                array(90, 45), array(-90, -45), array(12.3456789012345, 180)) as $coordinates) {
            $center = new GeoPoint($coordinates[0], $coordinates[1]);
            foreach (array(0, '0') as $distance) {
                $box = $center->boundingBox($distance, 'km');
                $this->assertSame($center->getLatitude(), $box->getMinLatitude());
                $this->assertSame($center->getLatitude(), $box->getMaxLatitude());
                $this->assertSame($center->getLongitude(), $box->getMinLongitude());
                $this->assertSame($center->getLongitude(), $box->getMaxLongitude());
                $this->assertTrue($box->contains($center));
            }
        }
        $box = (new GeoPoint(0, 0))->boundingBox(0, 'km');
        $this->assertFalse($box->contains(new GeoPoint(0.000001, 0)));
        $this->assertFalse($box->contains(new GeoPoint(0, 0.000001)));
    }

    public function testPoleAndWholeWorldBoundsContainExpectedPoints()
    {
        $box = (new GeoPoint(89.9, 45))->boundingBox(100, 'km');
        $this->assertEquals(-180, $box->getMinLongitude());
        $this->assertEquals(180, $box->getMaxLongitude());
        $this->assertTrue($box->contains(new GeoPoint(90, -180)));
        $this->assertTrue($box->contains(new GeoPoint(90, 180)));

        $box = (new GeoPoint(0, 0))->boundingBox(M_PI * Earth::RADIUS_KM, 'km');
        foreach (array(-90, 0, 90) as $latitude) {
            foreach (array(-180, 0, 180) as $longitude) {
                $this->assertTrue($box->contains(new GeoPoint($latitude, $longitude)));
            }
        }
    }

    public function testInvalidBoundingBoxDistancesThrowLibraryExceptions()
    {
        $point = new GeoPoint(0, 0);
        foreach (array(-1, null, false, true, '', 'far', array(), new \stdClass(), INF, -INF, NAN, '1e9999') as $distance) {
            try {
                $point->boundingBox($distance, 'km');
                $this->fail('Invalid bounding box distance was accepted.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function testPolygonContainmentRetainsItsHalfOpenBoundaryConvention()
    {
        $polygon = Polygon::fromArray(array(array(10, 30), array(10, 40), array(20, 40), array(20, 30)));
        $this->assertTrue($polygon->containsGeoPoint(new GeoPoint(15, 35)));
        $this->assertFalse($polygon->containsGeoPoint(new GeoPoint(15, 45)));
        $this->assertFalse($polygon->containsGeoPoint(new GeoPoint(15, 30)));
        $this->assertFalse($polygon->containsGeoPoint(new GeoPoint(10, 35)));
        $this->assertTrue($polygon->containsGeoPoint(new GeoPoint(15, 40)));
        $this->assertTrue($polygon->containsGeoPoint(new GeoPoint(20, 35)));
    }

    public function testPolygonRayCastingSupportsConcaveAndClosedRings()
    {
        $vertices = array(array(0, 0), array(0, 4), array(1, 4), array(1, 1), array(4, 1), array(4, 0));
        foreach (array($vertices, array_reverse($vertices), array_merge($vertices, array($vertices[0]))) as $ring) {
            $polygon = Polygon::fromArray($ring);
            $this->assertTrue($polygon->containsGeoPoint(new GeoPoint(0.5, 3)));
            $this->assertTrue(Polygon::pointInPolygon(new GeoPoint(3, 0.5), $polygon));
            $this->assertFalse((new GeoPoint(2, 2))->inPolygon($polygon));
        }
    }

    public function testPolygonCoordinatesNearTheEquatorDoNotRequireDecimalStrings()
    {
        $polygon = Polygon::fromArray(array(
            array(-0.00001, 10), array(0.5, 11), array(1, 11), array(1, 10)
        ));
        $this->assertTrue($polygon->containsGeoPoint(new GeoPoint(0.3, 10.1)));
        $this->assertFalse($polygon->containsGeoPoint(new GeoPoint(-0.00002, 10.1)));
        $this->assertFalse($polygon->containsGeoPoint(new GeoPoint(0.3, 11.1)));
    }

    public function testMalformedPolygonPairsThrowLibraryExceptions()
    {
        foreach (array(null, 0, '0,0', new \stdClass(), array(), array(0), array(0, 0, 0),
                array('lat' => 0, 'lon' => 0), array(1 => 0, 2 => 0)) as $vertex) {
            try {
                Polygon::fromArray(array($vertex));
                $this->fail('Malformed polygon vertex was accepted.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function testInvalidPolygonCoordinatesThrowLibraryExceptions()
    {
        foreach (array(null, false, true, '', 'east', array(), new \stdClass(), INF, -INF, NAN, '1e9999') as $coordinate) {
            foreach (array(array($coordinate, 0), array(0, $coordinate)) as $vertex) {
                try {
                    Polygon::fromArray(array($vertex));
                    $this->fail('Invalid polygon coordinate was accepted.');
                } catch (InvalidArgumentException $exception) {
                    $this->assertNotEmpty($exception->getMessage());
                }
            }
        }
    }

    public function testIncompletePolygonsRejectContainmentButAllowConstruction()
    {
        $point = new GeoPoint(1, 1);
        $polygon = new Polygon();
        for ($count = 0; $count < 3; $count++) {
            try {
                $polygon->containsGeoPoint($point);
                $this->fail('An incomplete polygon allowed containment.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
            $polygon->addVertex(new GeoPoint($count, $count));
        }
        $this->assertCount(3, $polygon->getVertices());

        $polygon = Polygon::fromArray(array(array(0, 0)));
        $this->assertCount(1, $polygon->getVertices());
        $this->expectException('AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException');
        Polygon::pointInPolygon($point, $polygon);
    }
}
