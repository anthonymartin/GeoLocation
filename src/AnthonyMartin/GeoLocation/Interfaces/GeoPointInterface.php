<?php

namespace AnthonyMartin\GeoLocation\Interfaces;
use AnthonyMartin\GeoLocation\Polygon;
use AnthonyMartin\GeoLocation\GeoPoint;

interface GeoPointInterface {
  public function inPolygon(Polygon $polygon);
  public function distanceTo(GeoPoint $geopoint, $unitofmeasure);
}

