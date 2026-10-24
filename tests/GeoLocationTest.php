<?php

require_once __DIR__ .'/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon;
use AnthonyMartin\GeoLocation\BoundingBox;
use AnthonyMartin\GeoLocation\GeoLocation;

class GeoLocationTest extends TestCase {

  public function testDistanceInKilometersBetweenGeopointsUsingCoordinates() {
    $geopointA = new GeoPoint(40.5187154, -74.4120953);
    $geopointB = new GeoPoint(40.65, -73.95);
    $distance = $geopointA->distanceTo($geopointB, 'km');
    $this->assertEqualsWithDelta(41.663681581973, $distance, 1e-9);
  }
  public function testDistanceInMilesBetweenGeopointsUsingCoordinates() {
    $geopointA = new GeoPoint(40.5187154, -74.4120953);
    $geopointB = new GeoPoint(40.65, -73.95);
    $distance = $geopointA->distanceTo($geopointB, 'miles');
    $this->assertEqualsWithDelta(25.888611494606, $distance, 1e-9);
  }
  public function testGettersOnGeoPoint() {
    $geopointA = new GeoPoint(40.5187154, -74.4120953);
    $this->assertEqualsWithDelta(40.5187154, $geopointA->getLatitude(), 1e-9);
    $this->assertEqualsWithDelta(-74.4120953, $geopointA->getLongitude(), 1e-9);
    $this->assertEqualsWithDelta(0.70718499240853, $geopointA->getLatitude(true), 1e-9);
    $this->assertEqualsWithDelta(-1.2987360662928, $geopointA->getLongitude(true), 1e-9);
  }
  public function testBoundingBox() {
    $geopointA = new GeoPoint(40.5187154, -74.4120953);
    $bbox =  $geopointA->boundingBox(3, 'mi');
    $this->assertEqualsWithDelta(-74.469211617725, $bbox->getMinLongitude(), 1e-9);
    $this->assertEqualsWithDelta(40.47529593323, $bbox->getMinLatitude(), 1e-9);
    $this->assertEqualsWithDelta(-74.354978982275, $bbox->getMaxLongitude(), 1e-9);
    $this->assertEqualsWithDelta(40.56213486677, $bbox->getMaxLatitude(), 1e-9);
    $this->assertInstanceOf(GeoPoint::class, $bbox->getVertices()[0]);
    $this->assertInstanceOf(GeoPoint::class, $bbox->getGeoPoints()[1]);
    $this->assertEqualsWithDelta(0.70642717975394, $bbox->getVertices()[0]->getLatitude(true), 1e-9);
    $this->assertEqualsWithDelta(-1.2997329340937, $bbox->getVertices()[0]->getLongitude(true), 1e-9);
    $this->assertEqualsWithDelta(40.47529593323, $bbox->getVertices()[0]->getLatitude(), 1e-9);
    $this->assertEqualsWithDelta(-74.469211617725, $bbox->getVertices()[0]->getLongitude(), 1e-9);
    $this->assertEqualsWithDelta(0.7079428050631269, $bbox->getVertices()[3]->getLatitude(true), 1e-9);
    $this->assertEqualsWithDelta(-1.2977391984918774, $bbox->getVertices()[2]->getLongitude(true), 1e-9);
    $this->assertEqualsWithDelta(40.56213486676994, $bbox->getVertices()[3]->getLatitude(), 1e-9);
    $this->assertEqualsWithDelta(-74.35497898227479, $bbox->getVertices()[2]->getLongitude(), 1e-9);

    $bbox = $geopointA->boundingBox(3, 'mi');
    $this->assertInstanceOf(GeoPoint::class, $bbox->getVertices()[2]);
    $this->assertInstanceOf(GeoPoint::class, $bbox->getVertices()[3]);
    $this->assertEqualsWithDelta(0.70642717975394, $bbox->getVertices()[0]->getLatitude(true), 1e-9);
    $this->assertEqualsWithDelta(-1.2997329340937, $bbox->getVertices()[0]->getLongitude(true), 1e-9);
    $this->assertEqualsWithDelta(40.47529593323, $bbox->getVertices()[0]->getLatitude(), 1e-9);
    $this->assertEqualsWithDelta(-74.469211617725, $bbox->getVertices()[0]->getLongitude(), 1e-9);
    $this->assertEqualsWithDelta(0.7079428050631269, $bbox->getVertices()[3]->getLatitude(true), 1e-9);
    $this->assertEqualsWithDelta(-1.2977391984918774, $bbox->getVertices()[2]->getLongitude(true), 1e-9);
    $this->assertEqualsWithDelta(40.56213486676994, $bbox->getVertices()[3]->getLatitude(), 1e-9);
    $this->assertEqualsWithDelta(-74.35497898227479, $bbox->getVertices()[2]->getLongitude(), 1e-9);

    $this->assertEquals(4, count($bbox->getVertices()));
  }
  public function testGeoPointIsInPolygon() {

    $geopointA = new GeoPoint( 48.2029047,16.3873319);
    $polygon = Polygon::fromArray($this->getVertices());
    $this->assertTrue($geopointA->inPolygon($polygon));
    $this->assertTrue($polygon->containsGeoPoint($geopointA));

  }
  public function testGeoPointIsInBoundingBox() {
    $geopointA = new GeoPoint(48.2029047, 16.3873319);
    $parkslope = $geopointA->boundingBox(5,'mi');
    $this->assertTrue($geopointA->inPolygon($parkslope->getPolygon()));
    $nyc = $geopointA->boundingBox(100,'mi');
    $this->assertTrue($geopointA->inPolygon($nyc->getPolygon()));
    $usa = $geopointA->boundingBox(2500,'mi');
    $this->assertTrue($geopointA->inPolygon($usa->getPolygon()));
  }
  public function testOldGeoLocation() {

    $geopointA = GeoLocation::fromDegrees(48.2029047,16.3873319);
    $boundingBoxA = $geopointA->boundingCoordinates(100, 'km');

    $geopointB = new GeoPoint(48.2029047,16.3873319);
    $boundingBoxB = $geopointB->boundingBox(100,'km');

    $this->assertEquals($boundingBoxA[0]->getLatitudeInDegrees(), $boundingBoxB->getMinLatitude());
    $this->assertEquals($boundingBoxA[0]->getLongitudeInDegrees(), $boundingBoxB->getMinLongitude());
    $this->assertEquals($boundingBoxA[1]->getLatitudeInDegrees(), $boundingBoxB->getMaxLatitude());
    $this->assertEquals($boundingBoxA[1]->getLongitudeInDegrees(), $boundingBoxB->getMaxLongitude());


  }
  public function testGeopointIsFoundInsideBoundingBoxPolygon() {
    $point = new GeoPoint(40.5187154, -74.4120953);
    $polygon = $point->boundingBox(5, 'mi')->getPolygon();
    $this->assertCount(4, $polygon->getVertices());
    $this->assertTrue($point->inPolygon($polygon));
    $this->assertTrue($polygon->containsGeoPoint($point));
    $this->assertTrue(Polygon::pointInPolygon($point, $polygon));
  }
  public function testDegreeToRadianConversion() {
    $gp = new GeoPoint(40.59108117795, 0);
    $this->assertEqualsWithDelta(0.70844801349953, $gp->getLatitude(true), 1e-9);

    $polygon = Polygon::fromArray(array(array($gp->getLatitude(), $gp->getLongitude())));
    foreach($polygon->getVertices() as $geopoint) {
      $this->assertEqualsWithDelta(0.70844801349953, $geopoint->getLatitude(true), 1e-9);
    }

  }
  public function testIsInBoundingBox() {
    $point = new GeoPoint(40.7127753, -74.0059728);
    foreach ([5, 10] as $distance) {
      $box = $point->boundingBox($distance, 'mi');
      $this->assertTrue($box->getPolygon()->containsGeoPoint($point));
      $this->assertTrue($box->contains($point));
    }
  }

  public function testInvalidArgumentExceptionIsThrown() {
    $this->expectException(AnthonyMartin\GeoLocation\Exceptions\OutOfBoundsException::class);
    $geopoint = new GeoPoint(99999999,9999999);
  }
  protected function getVertices() {
    return  [[48.1958732, 16.370487],
        [48.1912459, 16.3733142],
        [48.1867045, 16.3760662],
        [48.186919, 16.377182],
        [48.184458, 16.384886],
        [48.179364, 16.385551],
        [48.177247, 16.386731],
        [48.1786901, 16.3915376],
        [48.1814898, 16.40114],
        [48.1839433, 16.409111],
        [48.1853167, 16.4139176],
        [48.1867616, 16.419003],
        [48.1880656, 16.4232625],
        [48.1909968, 16.4171953],
        [48.1964458, 16.4201832],
        [48.2003611, 16.4156565],
        [48.2093724, 16.4063559],
        [48.2167239, 16.3931894],
        [48.2161157, 16.3926635],
        [48.214461, 16.3929372],
        [48.2129348, 16.3926315],
        [48.2133639, 16.3905609],
        [48.2134212, 16.3884901],
        [48.2122159, 16.384888],
        [48.2091696, 16.3842308],
        [48.206666, 16.3825088],
        [48.2071572, 16.380685],
        [48.206751, 16.379516],
        [48.2028537, 16.3744998],
        [48.1983341, 16.3688982],
        [48.1958732, 16.370487]];
  }
}
