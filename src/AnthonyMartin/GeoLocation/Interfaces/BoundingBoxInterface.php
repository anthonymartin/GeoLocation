<?php

namespace AnthonyMartin\GeoLocation\Interfaces;

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation;

interface BoundingBoxInterface {
  public function __construct($geopoint, $distance, $unit_of_measurement);
  public function setGeoPoint(GeoPoint $geopoint);
  public function setUnit($unit);
  public function setDistance($distance);
  public function calculate();

}

