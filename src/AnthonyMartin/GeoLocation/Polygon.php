<?php

declare(strict_types=1);
namespace AnthonyMartin\GeoLocation;

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;

class Polygon implements Interfaces\Polygon {

  /** @var list<GeoPoint> */
  protected array $GeoPoints = array();

  public function addVertex(GeoPoint $geopoint): void {
    $this->GeoPoints[] = $geopoint;
  }

  /**
   * @param array<array{int|float|numeric-string, int|float|numeric-string}> $vertices
   * @return Polygon
   */
  public static function fromArray(array $vertices): Polygon {
    $polygon = new self;
    foreach($vertices as $vertex) {
      if (!is_array($vertex) || count($vertex) != 2 ||
          !array_key_exists(0, $vertex) || !array_key_exists(1, $vertex)) {
        throw new InvalidArgumentException('Each polygon vertex must be a latitude/longitude pair.');
      }
      foreach ($vertex as $coordinate) {
        if (!is_numeric($coordinate) || !is_finite((float) $coordinate)) {
          throw new InvalidArgumentException('Polygon coordinates must be finite numbers.');
        }
      }
      $polygon->addVertex(new GeoPoint($vertex[0],$vertex[1]));
    }
    return $polygon;
  }
  public function containsGeoPoint(GeoPoint $p): bool {
    return self::pointInPolygon($p, $this);
  }

  /*
  * Planar ray casting retains the half-open boundary convention: points on
  * polygon edges are not consistently included. Antimeridian-crossing rings
  * must not be used here; use BoundingBox::contains() for wrapped boxes.
  * @see https://stackoverflow.com/a/18190354
  */
  public static function pointInPolygon(GeoPoint $p, Polygon $polygon): bool {
    $vertices = $polygon->getVertices();
    $n = count($vertices);
    if ($n < 3) {
      return false;
    }
    $c = 0;
    $p1 = $vertices[0];
    for ($i=1; $i<=$n; $i++) {
      $p2 = $vertices[$i % $n];
      if (
          $p->getLongitude() > min($p1->getLongitude(), $p2->getLongitude())

          && $p->getLongitude() <= max($p1->getLongitude(), $p2->getLongitude())

          && $p->getLatitude() <= max($p1->getLatitude(), $p2->getLatitude())

          && $p1->getLongitude() != $p2->getLongitude())
      {

        $xinters = ($p->getLongitude() - $p1->getLongitude()) * ($p2->getLatitude() - $p1->getLatitude()) / ($p2->getLongitude() - $p1->getLongitude()) + $p1->getLatitude();
        if (($p1->getLatitude() == $p2->getLatitude()) ||
            ($p->getLatitude() <= $xinters)
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
   * @return list<GeoPoint>
   */
  public function getGeoPoints(): array
  {
    return $this->GeoPoints;
  }

  /**
   * @return list<GeoPoint>
   */
  public function getVertices(): array
  {
    return $this->GeoPoints;
  }
}
