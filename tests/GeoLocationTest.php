<?php

require __DIR__ .'/../vendor/autoload.php';

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
    $this->assertEquals(41.663681581973, $distance);
  }
  public function testDistanceInMilesBetweenGeopointsUsingCoordinates() {
    $geopointA = new GeoPoint(40.5187154, -74.4120953);
    $geopointB = new GeoPoint(40.65, -73.95);
    $distance = $geopointA->distanceTo($geopointB, 'miles');
    $this->assertEquals(25.888611494606, $distance);
  }
  public function testGettersOnGeoPoint() {
    $geopointA = new GeoPoint(40.5187154, -74.4120953);
    $this->assertEquals(40.5187154, $geopointA->getLatitude());
    $this->assertEquals(-74.4120953, $geopointA->getLongitude());
    $this->assertEquals(0.70718499240853, $geopointA->getLatitude(true));
    $this->assertEquals(-1.2987360662928, $geopointA->getLongitude(true));
  }
  public function testBoundingBox() {
    $geopointA = new GeoPoint(40.5187154, -74.4120953);
    $bbox =  $geopointA->boundingBox(3, 'mi');
    $this->assertEquals(-74.469211617725, $bbox->getMinLongitude());
    $this->assertEquals(40.47529593323, $bbox->getMinLatitude());
    $this->assertEquals(-74.354978982275, $bbox->getMaxLongitude());
    $this->assertEquals(40.56213486677, $bbox->getMaxLatitude());
    $this->assertTrue(true, is_a($bbox->getVertices()[0], "GeoPoint"));
    $this->assertTrue(true, is_a($bbox->getGeoPoints()[1], "GeoPoint"));
    $this->assertEquals(0.70642717975394, $bbox->getVertices()[0]->getLatitude(true));
    $this->assertEquals(-1.2997329340937, $bbox->getVertices()[0]->getLongitude(true));
    $this->assertEquals(40.47529593323, $bbox->getVertices()[0]->getLatitude());
    $this->assertEquals(-74.469211617725, $bbox->getVertices()[0]->getLongitude());
    $this->assertEquals(0.7079428050631269, $bbox->getVertices()[3]->getLatitude(true));
    $this->assertEquals(-1.2977391984918774, $bbox->getVertices()[3]->getLongitude(true));
    $this->assertEquals(40.56213486676994, $bbox->getVertices()[3]->getLatitude());
    $this->assertEquals(-74.35497898227479, $bbox->getVertices()[3]->getLongitude());

    $bbox = $geopointA->boundingBox(3, 'mi');
    $this->assertTrue(true, is_a($bbox->getVertices()[2], "GeoPoint"));
    $this->assertTrue(true, is_a($bbox->getVertices()[3], "GeoPoint"));
    $this->assertEquals(0.70642717975394, $bbox->getVertices()[0]->getLatitude(true));
    $this->assertEquals(-1.2997329340937, $bbox->getVertices()[0]->getLongitude(true));
    $this->assertEquals(40.47529593323, $bbox->getVertices()[0]->getLatitude());
    $this->assertEquals(-74.469211617725, $bbox->getVertices()[0]->getLongitude());
    $this->assertEquals(0.7079428050631269, $bbox->getVertices()[3]->getLatitude(true));
    $this->assertEquals(-1.2977391984918774, $bbox->getVertices()[3]->getLongitude(true));
    $this->assertEquals(40.56213486676994, $bbox->getVertices()[3]->getLatitude());
    $this->assertEquals(-74.35497898227479, $bbox->getVertices()[3]->getLongitude());

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

    $geopointA = new GeoPoint(40.5187154, -74.4120953);
    $bbox = $geopointA->boundingBox(5, 'mi');
    $polygon3 = array(
        [$bbox->getVertices()[0]->getLatitude(), $bbox->getVertices()[0]->getLongitude()],
        [$bbox->getVertices()[1]->getLatitude(), $bbox->getVertices()[1]->getLongitude()],
        [$bbox->getVertices()[2]->getLatitude(), $bbox->getVertices()[2]->getLongitude()],
        [$bbox->getVertices()[3]->getLatitude(), $bbox->getVertices()[3]->getLongitude()]
    );

    /*
     * We're generating an array of GeoPoints which is basically what a Polygon object is.
     */
    $polygonB = array(
        new GeoPoint(40.44634962205,-74.507289174707),
        new GeoPoint(40.44634962205,-74.316901425293),
        new GeoPoint(40.59108117795,-74.507289174707),
        new GeoPoint(40.59108117795,-74.316901425293)
    );

    $polygonA = Polygon::fromArray(array(
        array($bbox->getVertices()[0]->getLatitude(), $bbox->getVertices()[0]->getLongitude()),
        array($bbox->getVertices()[1]->getLatitude(), $bbox->getVertices()[1]->getLongitude()),
        array($bbox->getVertices()[2]->getLatitude(), $bbox->getVertices()[2]->getLongitude()),
        array($bbox->getVertices()[3]->getLatitude(), $bbox->getVertices()[3]->getLongitude())
    ));

    $this->assertEquals(40.59108117795, $polygonA->getVertices()[2]->getLatitude());
    $this->assertEquals(0.70844801349953, $polygonA->getVertices()[2]->getLatitude(true));
    $this->assertEquals(0.70844801349953, 0.70844801349952);
    $this->assertEquals(40.59108117795, $polygonA->getVertices()[3]->getLatitude());
    $this->assertEquals(0.70844801349953, $polygonA->getVertices()[3]->getLatitude(true));


    $this->comparePolygons($polygonA, $polygonB);
    $this->assertTrue(Polygon::pointInPolygon($geopointA, $polygonA));

    $this->assertTrue($geopointA->inPolygon($polygonA));
    $this->assertTrue($polygonA->containsGeoPoint($geopointA));
    $this->assertTrue(Polygon::pointInPolygon($geopointA, $polygonA));


  }
  protected function comparePolygons($polygonA, $polygonB) {
    $pA = array();
    $pB = array();

    $pA = json_decode(json_encode($polygonA->getVertices()), true);
    foreach ($polygonB as $geopointB) {
      $pB[] = get_object_vars($geopointB);
    }

  }
  public function testDegreeToRadianConversion() {
    $gp = new GeoPoint(40.59108117795, 0);
    $this->assertEquals(0.70844801349953, $gp->getLatitude(true));

    $polygon = Polygon::fromArray(array(array($gp->getLatitude(), $gp->getLongitude())));
    foreach($polygon as $geopoint) {
      $this->assertEquals(0.70844801349953, $geopoint->getLatitude(true));
    }

  }
  public function testIsInBoundingBox() {
    $geopointA = new GeoPoint(40.7127753, -74.0059728);
    $bbox = $geopointA->boundingBox(5, 'mi');
    $polygon = Polygon::fromArray([
        [$bbox->getVertices()[0]->getLatitude(), $bbox->getVertices()[0]->getLongitude()],
        [$bbox->getVertices()[1]->getLatitude(), $bbox->getVertices()[1]->getLongitude()],
        [$bbox->getVertices()[2]->getLatitude(), $bbox->getVertices()[2]->getLongitude()],
        [$bbox->getVertices()[3]->getLatitude(), $bbox->getVertices()[3]->getLongitude()]
    ]);


    $this->assertTrue($polygon->containsGeoPoint($geopointA));

    $geopointA = new GeoPoint(40.7127753, -74.0059728);
    $bbox = $geopointA->boundingBox(10, 'mi');
    $polygon = Polygon::fromArray([
        [$bbox->getVertices()[0]->getLatitude(), $bbox->getVertices()[0]->getLongitude()],
        [$bbox->getVertices()[1]->getLatitude(), $bbox->getVertices()[1]->getLongitude()],
        [$bbox->getVertices()[3]->getLatitude(), $bbox->getVertices()[3]->getLongitude()],
        [$bbox->getVertices()[2]->getLatitude(), $bbox->getVertices()[2]->getLongitude()]
    ]);
    $this->assertTrue($polygon->containsGeoPoint($geopointA));

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
