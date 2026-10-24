<?php

namespace AnthonyMartin\GeoLocation\Interfaces;
use AnthonyMartin\GeoLocation\GeoPoint;

interface GeoPointInterface {
  public function inPolygon(\AnthonyMartin\GeoLocation\Polygon $polygon);
  public function distanceTo(GeoPoint $geopoint, $unitofmeasure);
}

