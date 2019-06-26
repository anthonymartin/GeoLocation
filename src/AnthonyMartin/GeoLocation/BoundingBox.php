<?php
namespace AnthonyMartin\GeoLocation;

use AnthonyMartin\GeoLocation\Earth;
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;
use AnthonyMartin\GeoLocation\Exceptions\InvalidBoundingBoxCoordinatesException;

class BoundingBox {

  protected $minLon;
  protected $minLat;
  protected $maxLon;
  protected $maxLat;
  protected $GeoPoints = [];


  public function __construct($minLat, $minLon, $maxLat, $maxLon, $inRadians=false)
  {
    if (!$minLat||!$minLon||!$maxLat||!$maxLon) {
      throw new InvalidBoundingBoxCoordinatesException;
    }
    $this->minLat = ($inRadians) ? rad2deg($minLat) : $minLat;
    $this->minLon = ($inRadians) ? rad2deg($minLon) : $minLon;
    $this->maxLat = ($inRadians) ? rad2deg($maxLat) : $maxLat;
    $this->maxLon = ($inRadians) ? rad2deg($maxLon) : $maxLon;
    $this->GeoPoints[] = new GeoPoint($minLat, $minLon, $inRadians);
    $this->GeoPoints[] = new GeoPoint($minLat, $maxLon, $inRadians);
    $this->GeoPoints[] = new GeoPoint($maxLat, $minLon, $inRadians);
    $this->GeoPoints[] = new GeoPoint($maxLat, $maxLon, $inRadians);
  }


  /**
   * <p>Computes the bounding coordinates of all points on the surface
   * of a sphere that have a great circle distance to the point represented
   * by this GeoLocation instance that is less or equal to the distance
   * argument.</p>
   * <p>For more information about the formulae used in this method visit
   * <a href="http://JanMatuschek.de/LatitudeLongitudeBoundingCoordinates">
   * http://JanMatuschek.de/LatitudeLongitudeBoundingCoordinates</a>.</p>
   *
   * @param double $distance the distance from the point represented by this
   * GeoLocation instance. Must me measured in the same unit as the radius
   * argument.
   * @param string $unit_of_measurement
   * @throws \Exception
   * @internal param radius the radius of the sphere, e.g. the average radius for a
   * spherical approximation of the figure of the Earth is approximately
   * 6371.01 kilometers.
   * @return GeoLocation[] an array of two GeoLocation objects such that:<ul>
   * <li>The latitude of any point within the specified distance is greater
   * or equal to the latitude of the first array element and smaller or
   * equal to the latitude of the second array element.</li>
   * <li>If the longitude of the first array element is smaller or equal to
   * the longitude of the second element, then
   * the longitude of any point within the specified distance is greater
   * or equal to the longitude of the first array element and smaller or
   * equal to the longitude of the second array element.</li>
   * <li>If the longitude of the first array element is greater than the
   * longitude of the second element (this is the case if the 180th
   * meridian is within the distance), then
   * the longitude of any point within the specified distance is greater
   * or equal to the longitude of the first array element
   * <strong>or</strong> smaller or equal to the longitude of the second
   * array element.</li>
   * </ul>
   */
  public static function fromGeoPoint(GeoPoint $geopoint, $distance, $unit_of_measurement) {
		$radius = Earth::getRadius($unit_of_measurement);
		if ($radius < 0 || $distance < 0) throw new InvalidArgumentException('Bounding box distance must be greater than or equal to 0.');

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
  public function getMinLongitude()
  {
    return $this->minLon;
  }

  /**
   * @return float
   */
  public function getMinLatitude()
  {
    return $this->minLat;
  }

  /**
   * @return float
   */
  public function getMaxLongitude()
  {
    return $this->maxLon;
  }

  /**
   * @return float
   */
  public function getMaxLatitude()
  {
    return $this->maxLat;
  }

  /**
   * @return array of GeoPoint
   */
  public function getVertices()
  {
    return $this->GeoPoints;
  }

  /**
   * @return array of GeoPoint
   */
  public function getGeoPoints()
  {
    return $this->GeoPoints;
  }

  /**
   * @return Polygon from bounding box coordinates
   */
  public function getPolygon() {
    $polygon = new Polygon();
    foreach($this->getVertices() as $vertex) {
      $polygon->addVertex($vertex);
    }
    return $polygon;
  }

}
