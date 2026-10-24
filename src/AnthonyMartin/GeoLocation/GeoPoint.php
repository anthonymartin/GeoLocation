<?php

namespace AnthonyMartin\GeoLocation;
use AnthonyMartin\GeoLocation\Exceptions\NoApiKeyException;
use AnthonyMartin\GeoLocation\Polygon;
use AnthonyMartin\GeoLocation\Earth;
use AnthonyMartin\GeoLocation\BoundingBox;
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;
use AnthonyMartin\GeoLocation\Exceptions\OutOfBoundsException;
use AnthonyMartin\GeoLocation\Exceptions\UnexpectedResponseException;

class GeoPoint implements Interfaces\GeoPointInterface {

  protected $radLat;  // latitude in radians
  protected $radLon;  // longitude in radians
  protected $degLat;	 // latitude in degrees
  protected $degLon;  // longitude in degrees

  /**
   * @param double $latitude the latitude
   * @param double $longitude the longitude
   * @param bool $inRadians true if latitude and longitude are in radians
   * @return GeoPoint
   */
  public function __construct($latitude, $longitude, $inRadians=false) {
    self::normalizeCoordinate($latitude, 'Latitude',
      $inRadians ? Earth::getMINLAT() : -90, $inRadians ? Earth::getMAXLAT() : 90);
    self::normalizeCoordinate($longitude, 'Longitude',
      $inRadians ? Earth::getMINLON() : -180, $inRadians ? Earth::getMAXLON() : 180);
    if($inRadians) {
      $this->radLat = $latitude;
      $this->radLon = $longitude;
      $this->degLat = rad2deg((float) $latitude);
      $this->degLon = rad2deg((float) $longitude);
    } else {
      $this->radLat = deg2rad((float) $latitude);
      $this->radLon = deg2rad((float) $longitude);
      $this->degLat = $latitude;
      $this->degLon = $longitude;
    }
    $this->checkBounds();
  }

	public function boundingBox($distance, $unit_of_measurement) {
	  return BoundingBox::fromGeoPoint($this, $distance, $unit_of_measurement);
  }
  /**
   * Computes the great circle distance between this point and the given point.
   * @param GeoPoint $geopoint
   * @param string $unit_of_measurement
   * @internal param float $radius the radius of the sphere, e.g. the average radius for a
   * spherical approximation of the figure of the Earth is approximately
   * 6371.01 kilometers.
   * @return double the distance, measured in the same unit as the radius
   * argument.
   */
  public function distanceTo(GeoPoint $geopoint, $unit_of_measurement) {
    $radius = Earth::getRadius($unit_of_measurement);

    $latitudeDelta = $geopoint->radLat - $this->radLat;
    $longitudeDelta = $geopoint->radLon - $this->radLon;
    $latitudeTerm = sin($latitudeDelta / 2);
    $longitudeTerm = sin($longitudeDelta / 2);
    $haversine = $latitudeTerm * $latitudeTerm +
      cos($this->radLat) * cos($geopoint->radLat) * $longitudeTerm * $longitudeTerm;
    // Floating point rounding can push antipodal points outside the domain.
    $haversine = max(0.0, min(1.0, $haversine));

    return 2 * atan2(sqrt($haversine), sqrt(1 - $haversine)) * $radius;
  }

  public function inPolygon(Polygon $polygon) {
    return $polygon->containsGeoPoint($this);
  }

  /** Input-type preservation is deprecated; 3.0 always returns float. */
  public function getLatitude($inRadians=false) {
    return (!$inRadians) ? $this->degLat : $this->radLat;
  }
  /** Input-type preservation is deprecated; 3.0 always returns float. */
  public function getLongitude($inRadians=false) {
    return (!$inRadians) ? $this->degLon : $this->radLon;
  }
  /**
   * @return float|int|string
   */
  public function getRadLat()
  {
    return $this->radLat;
  }

  /**
   * @param float|int|string $radLat
   */
  /** @deprecated Removed in 3.0; assign the new point returned by withLatitude($value, true). */
  public function setRadLat($radLat)
  {
    self::normalizeCoordinate($radLat, 'Latitude', Earth::getMINLAT(), Earth::getMAXLAT());
    $this->radLat = $radLat;
    $this->degLat = rad2deg((float) $radLat);
  }

  /**
   * @return float|int|string
   */
  public function getRadLon()
  {
    return $this->radLon;
  }

  /**
   * @param float|int|string $radLon
   */
  /** @deprecated Removed in 3.0; assign the new point returned by withLongitude($value, true). */
  public function setRadLon($radLon)
  {
    self::normalizeCoordinate($radLon, 'Longitude', Earth::getMINLON(), Earth::getMAXLON());
    $this->radLon = $radLon;
    $this->degLon = rad2deg((float) $radLon);
  }

  /**
   * @return mixed
   */
  public function getDegLat()
  {
    return $this->degLat;
  }

  /**
   * @param mixed $degLat
   */
  /** @deprecated Removed in 3.0; assign the new point returned by withLatitude($value). */
  public function setDegLat($degLat)
  {
    self::normalizeCoordinate($degLat, 'Latitude', -90, 90);
    $this->degLat = $degLat;
    $this->radLat = deg2rad((float) $degLat);
  }

  /**
   * @return mixed
   */
  public function getDegLon()
  {
    return $this->degLon;
  }

  /**
   * @param mixed $degLon
   */
  /** @deprecated Removed in 3.0; assign the new point returned by withLongitude($value). */
  public function setDegLon($degLon)
  {
    self::normalizeCoordinate($degLon, 'Longitude', -180, 180);
    $this->degLon = $degLon;
    $this->radLon = deg2rad((float) $degLon);
  }

  /**
   * Normalize finite numeric coordinates before arithmetic or state changes.
   * @throws InvalidArgumentException
   * @throws OutOfBoundsException
   */
  protected static function normalizeCoordinate($coordinate, $name, $minimum, $maximum) {
    if ((!is_int($coordinate) && !is_float($coordinate) && !is_string($coordinate)) ||
        !is_numeric($coordinate)) {
      throw new InvalidArgumentException($name . ' must be a finite numeric value');
    }
    $coordinate = (float) $coordinate;
    if (!is_finite($coordinate)) {
      throw new InvalidArgumentException($name . ' must be a finite numeric value');
    }
    if ($coordinate < $minimum || $coordinate > $maximum) {
      throw new OutOfBoundsException($name . ' must be between ' . $minimum . ' and ' . $maximum);
    }
    return $coordinate;
  }

  /**
   * @description checks lat and long are within bounds of our elipsoid
   * @throws OutOfBoundsException
   */
  protected function checkBounds() {
    if ($this->radLat < Earth::getMINLAT() || $this->radLat > Earth::getMAXLAT() ||
        $this->radLon < Earth::getMINLON() || $this->radLon > Earth::getMAXLON())
      throw new OutOfBoundsException("Check your latitude and longitude inputs");
  }

  /**
   * Create a point using the first result returned by Google, ordered by relevance.
   * @deprecated In 3.0 use (new GoogleGeocoder($apiKey))->geocode($address).
   *
   * @param string $address
   * @param string $apiKey
   * @return GeoPoint
   * @throws \AnthonyMartin\GeoLocation\Exceptions\CurlErrorException
   * @throws UnexpectedResponseException if Google sends us something that we don't expect. we only like nice presents not 500 errors and the like
   * @throws NoApiKeyException if you forget to pass a google API key. create one at https://console.cloud.google.com for Geocoding API
   */
  public static function fromAddress($address, $apiKey=null) {
    $response = GoogleGeocoder::geocode($address, $apiKey);
    $location = $response->results[0]->geometry->location;
    return new self($location->lat, $location->lng);
  }
}
