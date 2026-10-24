<?php

namespace AnthonyMartin\GeoLocation\Interfaces;

use AnthonyMartin\GeoLocation\GeoPoint;

interface BoundingBoxInterface {
  public static function fromGeoPoint(GeoPoint $geopoint, $distance, $unit_of_measurement);
  public function contains(GeoPoint $geopoint);
  public function getMinLatitude();
  public function getMinLongitude();
  public function getMaxLatitude();
  public function getMaxLongitude();
  public function getVertices();
  public function getGeoPoints();
  public function getPolygon();
  public function toPolygon();
}
