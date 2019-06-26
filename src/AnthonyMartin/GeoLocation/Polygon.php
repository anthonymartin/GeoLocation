<?php
namespace AnthonyMartin\GeoLocation;

use AnthonyMartin\GeoLocation\GeoPoint;

class Polygon {

  protected $GeoPoints = array();

  public function addVertex(GeoPoint $geopoint) {
    $this->GeoPoints[] = $geopoint;
  }

  /**
   * @param array $vertices expects array of arrays. eg. array(array(lat1,lon1), array(lat2, lon2))
   * @return Polygon
   */
  public static function fromArray(array $vertices) {
    $polygon = new self;
    foreach($vertices as $vertex) {
      $polygon->addVertex(new GeoPoint($vertex[0],$vertex[1]));
    }
    return $polygon;
  }
  public function containsGeoPoint(GeoPoint $p) {
    return self::pointInPolygon($p, $this);
  }

  /*
  * @see https://stackoverflow.com/a/18190354
  */
  public static function pointInPolygon(GeoPoint $p, Polygon $polygon) {
    $c = 0;
    $p1 = $polygon->getVertices()[0];
    $n = count($polygon->getVertices());
    for ($i=1; $i<=$n; $i++) {
      $p2 = $polygon->getVertices()[$i % $n];
      if (
          $p->getLongitude() > min($p1->getLongitude(), $p2->getLongitude())

          && $p->getLongitude() <= max($p1->getLongitude(), $p2->getLongitude())

          && $p->getLatitude() <= max($p1->getLatitude(), $p2->getLatitude())

          && $p1->getLongitude() != $p2->getLongitude())
      {

        $xinters = ($p->getLongitude() - $p1->getLongitude()) * ($p2->getLatitude() - $p1->getLatitude()) / ($p2->getLongitude() - $p1->getLongitude()) + $p1->getLatitude();
        $pLat = (string) $p->getLatitude();
        $p1Lat = (string) $p1->getLatitude();
        $p2Lat = (string) $p2->getLatitude();
        $xintersStr = (string) $xinters;
        if (($p1->getLatitude() == $p2->getLatitude()) ||
            ($p->getLatitude() <= $xinters) ||
            (bccomp("$p1Lat", "$p2Lat", 14) == 0) ||
            (bccomp("$pLat", "$xintersStr", 14) == 0) || // pLat == $xinters
            (bccomp("$pLat", "$xintersStr", 14) == -1)   // pLat < $xinters
        ) {
          $c++;
        }
      }
      $p1 = $p2;
    }
    // if the number of edges we passed through is even, then it's not in the poly.
    return $c%2!=0;
  }

  /**
   * @return array
   */
  public function getGeoPoints()
  {
    return $this->GeoPoints;
  }

  /**
   * @return array
   */
  public function getVertices()
  {
    return $this->GeoPoints;
  }
}
