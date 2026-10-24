<?php
namespace AnthonyMartin\GeoLocation;

/**
 * <p>Represents a point on the surface of a sphere. (The Earth is almost
 * spherical.)</p>
 *
 * <p>To create an instance, call one of the static methods fromDegrees() or
 * fromRadians().</p>
 *
 * <p>This is a PHP port of Java code that was originally published at
 * <a href="http://JanMatuschek.de/LatitudeLongitudeBoundingCoordinates#Java">
 * http://JanMatuschek.de/LatitudeLongitudeBoundingCoordinates#Java</a>.</p>
 *
 * Many thanks to the original author: Jan Philip Matuschek
 *
 * @author Anthony Martin
 * @version November 21 2012
 * @deprecated Removed in 3.0. Use GeoPoint, BoundingBox, and GoogleGeocoder.
 */
class GeoLocation implements Interfaces\GeoLocationInterface {

	protected $radLat;  // latitude in radians
	protected $radLon;  // longitude in radians

	protected $degLat;	 // latitude in degrees
	protected $degLon;  // longitude in degrees
	
	protected $angular = 0.0; // angular radius of the last boundingCoordinates call

	const EARTHS_RADIUS_KM = 6371.01;
	const EARTHS_RADIUS_MI = 3958.762079;

	protected static $MIN_LAT;  // -PI/2
	protected static $MAX_LAT;  //  PI/2
	protected static $MIN_LON;  // -PI
	protected static $MAX_LON;  //  PI

	public function __construct() {
		self::$MIN_LAT = Earth::getMINLAT();
		self::$MAX_LAT = Earth::getMAXLAT();
		self::$MIN_LON = Earth::getMINLON();
		self::$MAX_LON = Earth::getMAXLON();
	}

	/**
	 * @param double $latitude the latitude, in degrees.
	 * @param double $longitude the longitude, in degrees.
	 * @return GeoLocation
	 */
	public static function fromDegrees($latitude, $longitude) {
		$point = new GeoPoint($latitude, $longitude);
		$location = new self();
		$location->radLat = $point->getLatitude(true);
		$location->radLon = $point->getLongitude(true);
		$location->degLat = $point->getLatitude();
		$location->degLon = $point->getLongitude();
		return $location;
	}

	/**
	 * @param double $latitude the latitude, in radians.
	 * @param double $longitude the longitude, in radians.
	 * @return GeoLocation
	 */
	public static function fromRadians($latitude, $longitude) {
		$point = new GeoPoint($latitude, $longitude, true);
		$location = new self();
		$location->radLat = $point->getLatitude(true);
		$location->radLon = $point->getLongitude(true);
		$location->degLat = $point->getLatitude();
		$location->degLon = $point->getLongitude();
		return $location;
	}

	protected function checkBounds() {
		new GeoPoint($this->radLat, $this->radLon, true);
	}

  /**
   * Computes the great circle distance between this GeoLocation instance
   * and the location argument.
   * @param GeoLocation $location
   * @param string $unit_of_measurement
   * @internal param float $radius the radius of the sphere, e.g. the average radius for a
   * spherical approximation of the figure of the Earth is approximately
   * 6371.01 kilometers.
   * @return double the distance, measured in the same unit as the radius
   * argument.
   */
	public function distanceTo(GeoLocation $location, $unit_of_measurement) {
		$point = new GeoPoint($this->radLat, $this->radLon, true);
		$other = new GeoPoint($location->radLat, $location->radLon, true);
		return $point->distanceTo($other, $unit_of_measurement);
	}

	/**
	 * @return double the latitude, in degrees.
	 */
	public function getLatitudeInDegrees() {
		return $this->degLat;
	}

	/**
	 * @return double the longitude, in degrees.
	 */
	public function getLongitudeInDegrees() {
		return $this->degLon;
	}

	/**
	 * @return double the latitude, in radians.
	 */
	public function getLatitudeInRadians() {
		return $this->radLat;
	}
	
	/**
	 * @return double the longitude, in radians.
	 */
	public function getLongitudeInRadians() {
		return $this->radLon;
	}
	
	/**
	 * @return double angular radius in radians from the last successful
	 * boundingCoordinates() call, or 0.0 before a bounding calculation.
	 */
	public function getAngular() {
		return $this->angular;
	}

	public function __toString() {
		return "(" . $this->degLat . ", " . $this->degLon . ") = (" .
				$this->radLat . " rad, " . $this->radLon . " rad)";
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
	public function boundingCoordinates($distance, $unit_of_measurement) {
		$point = new GeoPoint($this->radLat, $this->radLon, true);
		$bounds = $point->boundingBox($distance, $unit_of_measurement);
		$this->angular = $distance / $this->getEarthsRadius($unit_of_measurement);
		return array(
			self::fromDegrees($bounds->getMinLatitude(), $bounds->getMinLongitude()),
			self::fromDegrees($bounds->getMaxLatitude(), $bounds->getMaxLongitude())
		);
	}

	protected function getEarthsRadius($unit_of_measurement) {
		return Earth::getRadius($unit_of_measurement);
	}
	/**
	 *  Returns Google's raw, unvalidated decoded response, or null on failure
	 * @deprecated Removed in 3.0; use GoogleGeocoder::geocode() and handle exceptions.
	 *  eg. $response = GeoLocation::getGeocodeFromGoogle($location, $key);
	 *		$latitude = $response->results[0]->geometry->location->lat;
	 *	    $longitude = $response->results[0]->geometry->location->lng;
	 *	@param string $location address, city, state, etc.
	 *	@param string $key - the key provided by Google
	 *	@return mixed
	 */
	public static function getGeocodeFromGoogle($location, $key=null) {
		return GoogleGeocoder::request($location, $key);
	}
	public static function MilesToKilometers($miles) {
		return $miles * 1.6093439999999999;
	}
	public static function KilometersToMiles($km) {
		return $km * 0.621371192237334;
	}
}
