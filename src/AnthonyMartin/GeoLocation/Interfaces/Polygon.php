<?php

namespace AnthonyMartin\GeoLocation\Interfaces;

use AnthonyMartin\GeoLocation\GeoPoint;

interface Polygon {
  public function addVertex(GeoPoint $geopoint);
  public static function fromArray(array $vertices);
  public function containsGeoPoint(GeoPoint $geopoint);
  public function getVertices();
  public function getGeoPoints();
}
