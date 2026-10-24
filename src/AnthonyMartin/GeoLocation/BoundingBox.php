<?php

declare(strict_types=1);
namespace AnthonyMartin\GeoLocation;

use AnthonyMartin\GeoLocation\Interfaces\GeoPointInterface;
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;
use AnthonyMartin\GeoLocation\Exceptions\InvalidBoundingBoxCoordinatesException;

class BoundingBox implements Interfaces\BoundingBoxInterface {

  protected float $minLon;
  protected float $minLat;
  protected float $maxLon;
  protected float $maxLat;
  /** @var list<GeoPoint> */
  protected array $GeoPoints = [];


  /**
   * @param int|float|numeric-string $minLat
   * @param int|float|numeric-string $minLon
   * @param int|float|numeric-string $maxLat
   * @param int|float|numeric-string $maxLon
   */
  public function __construct(mixed $minLat, mixed $minLon, mixed $maxLat, mixed $maxLon, bool $inRadians=false)
  {
    foreach (array($minLat, $minLon, $maxLat, $maxLon) as $coordinate) {
      if (!is_numeric($coordinate) || !is_finite((float) $coordinate)) {
        throw new InvalidBoundingBoxCoordinatesException('Bounding box coordinates must be finite numbers.');
      }
    }
    if ($minLat > $maxLat) {
      throw new InvalidBoundingBoxCoordinatesException('Minimum latitude must not exceed maximum latitude.');
    }
    $this->minLat = ($inRadians) ? rad2deg((float) $minLat) : (float) $minLat;
    $this->minLon = ($inRadians) ? rad2deg((float) $minLon) : (float) $minLon;
    $this->maxLat = ($inRadians) ? rad2deg((float) $maxLat) : (float) $maxLat;
    $this->maxLon = ($inRadians) ? rad2deg((float) $maxLon) : (float) $maxLon;
    $this->GeoPoints[] = new GeoPoint($minLat, $minLon, $inRadians);
    $this->GeoPoints[] = new GeoPoint($minLat, $maxLon, $inRadians);
    $this->GeoPoints[] = new GeoPoint($maxLat, $maxLon, $inRadians);
    $this->GeoPoints[] = new GeoPoint($maxLat, $minLon, $inRadians);
  }


  /**
   * Enclose a nonnegative great-circle search radius in a latitude/longitude box.
   * The box can include points farther from the center than the radius.
   * @param int|float|numeric-string $distance
   * @param string $unit_of_measurement
   */
  public static function fromGeoPoint(GeoPointInterface $geopoint, mixed $distance, mixed $unit_of_measurement): BoundingBox {
		$radius = Earth::getRadius($unit_of_measurement);
		if (!is_numeric($distance) || !is_finite((float) $distance) || $distance < 0) {
      throw new InvalidArgumentException('Bounding box distance must be a finite number greater than or equal to 0.');
    }

    // Preserve the original coordinates, including at the poles, without a
    // radians round trip that can move a zero-size box away from its center.
    if ($distance == 0) {
      return new BoundingBox($geopoint->getLatitude(), $geopoint->getLongitude(),
          $geopoint->getLatitude(), $geopoint->getLongitude());
    }

		// angular distance in radians on a great circle
		$angularDistance = $distance / $radius;

		$minLat = $geopoint->getLatitude(true) - $angularDistance;
		$maxLat = $geopoint->getLatitude(true) + $angularDistance;

		$minLon = 0;
		$maxLon = 0;
		if ($minLat > Earth::getMINLAT() && $maxLat < Earth::getMAXLAT()) {
			$deltaLon = asin(sin($angularDistance) / cos($geopoint->getLatitude(true)));
			$minLon = $geopoint->getLongitude(true) - $deltaLon;
			if ($minLon < Earth::getMINLON()) $minLon += 2 * pi();
			$maxLon = $geopoint->getLongitude(true) + $deltaLon;
			if ($maxLon > Earth::getMAXLON()) $maxLon -= 2 * pi();
		} else {
			// a pole is within the distance
			$minLat = max($minLat, Earth::getMINLAT());
			$maxLat = min($maxLat, Earth::getMAXLAT());
			$minLon = Earth::getMINLON();
			$maxLon = Earth::getMAXLON();
		}
    return new BoundingBox($minLat, $minLon, $maxLat, $maxLon, true);
  }
  /**
   * @return float
   */
  public function getMinLongitude(): float
  {
    return $this->minLon;
  }

  /**
   * @return float
   */
  public function getMinLatitude(): float
  {
    return $this->minLat;
  }

  /**
   * @return float
   */
  public function getMaxLongitude(): float
  {
    return $this->maxLon;
  }

  /**
   * @return float
   */
  public function getMaxLatitude(): float
  {
    return $this->maxLat;
  }

  /**
   * Perimeter corner order: SW, SE, NE, NW.
   * @return list<GeoPoint>
   */
  public function getVertices(): array
  {
    return $this->GeoPoints;
  }

  /**
   * Perimeter corner order: SW, SE, NE, NW.
   * @return list<GeoPoint>
   */
  public function getGeoPoints(): array
  {
    return $this->GeoPoints;
  }

  /** @return GeoPoint */
  public function getSouthWest(): GeoPoint { return $this->GeoPoints[0]; }

  /** @return GeoPoint */
  public function getSouthEast(): GeoPoint { return $this->GeoPoints[1]; }

  /** @return GeoPoint */
  public function getNorthWest(): GeoPoint { return $this->GeoPoints[3]; }

  /** @return GeoPoint */
  public function getNorthEast(): GeoPoint { return $this->GeoPoints[2]; }

  /**
   * Corners in perimeter order: SW, SE, NE, NW.
   * Antimeridian-crossing boxes still require contains() for containment.
   * @return list<GeoPoint>
   */
  public function getPerimeter(): array {
    return $this->getVertices();
  }

  /**
   * Test inclusive latitude/longitude bounds. A minimum longitude greater
   * than the maximum longitude denotes a box crossing the antimeridian.
   *
   * @return bool
   */
  public function contains(GeoPointInterface $point): bool
  {
    $latitude = $point->getLatitude();
    if ($latitude < $this->minLat || $latitude > $this->maxLat) {
      return false;
    }

    $longitude = $point->getLongitude();
    if ($this->containsLongitude($longitude)) {
      return true;
    }

    // Both longitude endpoints describe the same meridian.
    return abs($longitude) == 180 && $this->containsLongitude(-$longitude);
  }

  protected function containsLongitude(float $longitude): bool
  {
    if ($this->minLon <= $this->maxLon) {
      return $longitude >= $this->minLon && $longitude <= $this->maxLon;
    }
    return $longitude >= $this->minLon || $longitude <= $this->maxLon;
  }

  /**
   * @return Polygon from bounding box coordinates
   */
  public function getPolygon(): Polygon {
    return new Polygon(...$this->getPerimeter());
  }

  /**
   * @return Polygon
   */
  public function toPolygon(): Polygon {
    return $this->getPolygon();
  }

}
