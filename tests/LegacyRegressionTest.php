<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AnthonyMartin\GeoLocation\Earth;
use AnthonyMartin\GeoLocation\GeoLocation;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;
use AnthonyMartin\GeoLocation\Exceptions\InvalidUnitException;
use PHPUnit\Framework\TestCase;

class LegacyRegressionTest extends TestCase {
  public function testLegacyFactoriesAndInterfaceRemainAvailable() {
    $degrees = GeoLocation::fromDegrees(45, -90);
    $radians = GeoLocation::fromRadians(M_PI / 4, -M_PI / 2);
    $this->assertInstanceOf(AnthonyMartin\GeoLocation\Interfaces\GeoLocationInterface::class, $degrees);
    $this->assertEqualsWithDelta($degrees->getLatitudeInDegrees(), $radians->getLatitudeInDegrees(), 1.0e-12);
    $this->assertEqualsWithDelta($degrees->getLongitudeInDegrees(), $radians->getLongitudeInDegrees(), 1.0e-12);
    $this->assertEqualsWithDelta(M_PI / 4, $degrees->getLatitudeInRadians(), 1.0e-15);
    $this->assertEqualsWithDelta(-M_PI / 2, $degrees->getLongitudeInRadians(), 1.0e-15);
  }

  public function testLegacyAngularStartsAtZeroAndTracksLastSuccessfulBounds() {
    $location = GeoLocation::fromDegrees(0, 0);
    $this->assertSame(0.0, $location->getAngular());
    $location->boundingCoordinates(100, 'km');
    $expected = 100 / Earth::getRadius('km');
    $this->assertEqualsWithDelta($expected, $location->getAngular(), 1.0e-15);
    try {
      $location->boundingCoordinates(-1, 'km');
      $this->fail('Negative distance must be rejected.');
    } catch (InvalidArgumentException $exception) {
      $this->assertEqualsWithDelta($expected, $location->getAngular(), 1.0e-15);
    }
    $location->boundingCoordinates(0, 'km');
    $this->assertEquals(0.0, $location->getAngular());
  }

  public function testLegacyStringHasBalancedParentheses() {
    $this->assertSame('(0, 0) = (0 rad, 0 rad)', (string) GeoLocation::fromDegrees(0, 0));
  }

  public function testLegacyDistanceIsStableForSameAndNearbyPoints() {
    $location = GeoLocation::fromDegrees(48.2029047, 16.3873319);
    $this->assertSame(0.0, $location->distanceTo($location, 'km'));
    $nearby = GeoLocation::fromDegrees(48.20290471, 16.3873319);
    $distance = $location->distanceTo($nearby, 'km');
    $this->assertGreaterThan(0, $distance);
    $this->assertLessThan(0.000002, $distance);
    $this->assertTrue(is_finite($location->distanceTo(GeoLocation::fromDegrees(-48.2029047, -163.6126681), 'km')));
  }

  public function testLegacyBoundsMatchModernBoundsAtAntimeridianAndPoles() {
    foreach (array(array(0, 179.9, 100), array(89.9, 0, 100), array(0, 0, 0), array(30, 50, 50000)) as $case) {
      $legacy = GeoLocation::fromDegrees($case[0], $case[1]);
      $modern = new GeoPoint($case[0], $case[1]);
      $corners = $legacy->boundingCoordinates($case[2], 'km');
      $bounds = $modern->boundingBox($case[2], 'km');
      $this->assertCount(2, $corners);
      $this->assertInstanceOf(GeoLocation::class, $corners[0]);
      $this->assertInstanceOf(GeoLocation::class, $corners[1]);
      $this->assertEqualsWithDelta($bounds->getMinLatitude(), $corners[0]->getLatitudeInDegrees(), 1.0e-12);
      $this->assertEqualsWithDelta($bounds->getMinLongitude(), $corners[0]->getLongitudeInDegrees(), 1.0e-12);
      $this->assertEqualsWithDelta($bounds->getMaxLatitude(), $corners[1]->getLatitudeInDegrees(), 1.0e-12);
      $this->assertEqualsWithDelta($bounds->getMaxLongitude(), $corners[1]->getLongitudeInDegrees(), 1.0e-12);
    }
  }

  public function testLegacyFactoryRejectsNonFiniteCoordinates() {
    $this->expectException(InvalidArgumentException::class);
    GeoLocation::fromDegrees(NAN, 0);
  }

  public function testLegacyFactoryRejectsNonNumericCoordinates() {
    $this->expectException(InvalidArgumentException::class);
    GeoLocation::fromRadians('invalid', 0);
  }

  public function testLegacyDistanceUsesSharedUnitValidation() {
    $this->expectException(InvalidUnitException::class);
    $location = GeoLocation::fromDegrees(0, 0);
    $location->distanceTo($location, 'invalid');
  }

  public function testLegacyUnitConversionHelpersRemainCompatible() {
    $this->assertEqualsWithDelta(1.609344, GeoLocation::MilesToKilometers(1), 1.0e-12);
    $this->assertEqualsWithDelta(1, GeoLocation::KilometersToMiles(1.609344), 1.0e-12);
  }
}
