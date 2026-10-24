<?php

use AnthonyMartin\GeoLocation\Earth;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;
use AnthonyMartin\GeoLocation\Exceptions\OutOfBoundsException;
use PHPUnit\Framework\TestCase;

class GeoPointRegressionTest extends TestCase
{
    public function testSelfDistanceIsExactlyZeroForFixedAndDeterministicallySampledCoordinates()
    {
        $coordinates = array(
            array(0, 0), array(90, 180), array(-90, -180),
            array(40.5187154, -74.4120953), array(48.2029047, 16.3873319)
        );
        $seed = 1729;
        for ($index = 0; $index < 256; $index++) {
            $seed = ($seed * 48271) % 2147483647;
            $latitude = 180 * ($seed / 2147483647) - 90;
            $seed = ($seed * 48271) % 2147483647;
            $longitude = 360 * ($seed / 2147483647) - 180;
            $coordinates[] = array($latitude, $longitude);
        }
        foreach ($coordinates as $coordinate) {
            $point = new GeoPoint($coordinate[0], $coordinate[1]);
            $copy = new GeoPoint($coordinate[0], $coordinate[1]);
            foreach (array('km', 'kilometers', 'mi', 'miles') as $unit) {
                $this->assertSame(0.0, $point->distanceTo($point, $unit));
                $this->assertSame(0.0, $point->distanceTo($copy, $unit));
            }
        }
    }

    public function testVeryShortDistancesRemainPositiveAndAccurate()
    {
        $origin = new GeoPoint(0, 0);
        foreach (array(0.000000001, 0.0000001, 0.00001) as $angle) {
            foreach (array(new GeoPoint($angle, 0), new GeoPoint(0, $angle)) as $destination) {
                foreach (array('km', 'mi') as $unit) {
                    $expected = Earth::getRadius($unit) * deg2rad($angle);
                    $distance = $origin->distanceTo($destination, $unit);
                    $this->assertGreaterThan(0, $distance);
                    $this->assertEqualsWithDelta($expected, $distance, $expected * 1.0e-12);
                    $this->assertSame($distance, $destination->distanceTo($origin, $unit));
                }
            }
        }
    }

    public function testAntipodalAndNearlyAntipodalDistancesAreFiniteAndBounded()
    {
        foreach (array('km', 'mi') as $unit) {
            $maximum = M_PI * Earth::getRadius($unit);
            $origin = new GeoPoint(0, 0);
            $this->assertEqualsWithDelta($maximum, $origin->distanceTo(new GeoPoint(0, 180), $unit), 1.0e-8);
            $pairs = array(
                array(new GeoPoint(48.2029047, 16.3873319), new GeoPoint(-48.2029047, -163.6126681)),
                array(new GeoPoint(0, 0), new GeoPoint(0.000001, 179.999999))
            );
            foreach ($pairs as $pair) {
                $distance = $pair[0]->distanceTo($pair[1], $unit);
                $this->assertTrue(is_finite($distance));
                $this->assertGreaterThan(0, $distance);
                $this->assertLessThanOrEqual($maximum, $distance);
            }
        }
    }

    public function testDistanceStillValidatesUnitsForIdenticalPoints()
    {
        $point = new GeoPoint(0, 0);
        $this->expectException('InvalidArgumentException');
        $point->distanceTo($point, 'yards');
    }

    public function testNumericCoordinatesNormalizeToFloats()
    {
        $point = new GeoPoint('40.5', '-7.4e1');
        $this->assertSame(40.5, $point->getLatitude());
        $this->assertSame(-74.0, $point->getLongitude());
        $this->assertSame(deg2rad(40.5), $point->getLatitude(true));
        $this->assertSame(deg2rad(-74), $point->getLongitude(true));

        $radians = new GeoPoint('0.5', '-1', true);
        $this->assertSame(0.5, $radians->getRadLat());
        $this->assertSame(-1.0, $radians->getRadLon());
        $this->assertSame(rad2deg(0.5), $radians->getDegLat());
        $this->assertSame(rad2deg(-1), $radians->getDegLon());
        $this->assertSame(0.0, (new GeoPoint(0, 0))->getLatitude());
    }

    public function testConstructorsRejectInvalidTypesAndNonFiniteCoordinatesWithLibraryExceptions()
    {
        foreach ($this->invalidCoordinates() as $invalid) {
            foreach (array(false, true) as $inRadians) {
                foreach (array(array($invalid, 0), array(0, $invalid)) as $coordinate) {
                    try {
                        new GeoPoint($coordinate[0], $coordinate[1], $inRadians);
                        $this->fail('Invalid coordinate was accepted');
                    } catch (InvalidArgumentException $exception) {
                        $this->assertStringContainsString('finite numeric value', $exception->getMessage());
                    }
                }
            }
        }
    }

    public function testConstructorsAcceptInclusiveBoundsAndRejectOutOfRangeCoordinates()
    {
        foreach (array(array(-90, -180), array(90, 180)) as $coordinate) {
            $point = new GeoPoint($coordinate[0], $coordinate[1]);
            $radians = new GeoPoint(deg2rad($coordinate[0]), deg2rad($coordinate[1]), true);
            $this->assertSame($point->getLatitude(true), $radians->getLatitude(true));
            $this->assertSame($point->getLongitude(true), $radians->getLongitude(true));
        }
        foreach (array(array(90.01, 0), array(-90.01, 0), array(0, 180.01), array(0, -180.01)) as $coordinate) {
            foreach (array(false, true) as $inRadians) {
                try {
                    new GeoPoint(
                        $inRadians ? deg2rad($coordinate[0]) : $coordinate[0],
                        $inRadians ? deg2rad($coordinate[1]) : $coordinate[1],
                        $inRadians
                    );
                    $this->fail('Out of range coordinate was accepted');
                } catch (OutOfBoundsException $exception) {
                    $this->assertStringContainsString('must be between', $exception->getMessage());
                }
            }
        }
    }

    private function invalidCoordinates()
    {
        return array(null, true, false, '', 'north', array(), new stdClass(), NAN, INF, -INF, '1e309');
    }
}
