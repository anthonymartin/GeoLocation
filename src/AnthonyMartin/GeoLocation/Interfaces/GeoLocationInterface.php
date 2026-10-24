<?php
namespace AnthonyMartin\GeoLocation\Interfaces;

interface GeoLocationInterface {
	public static function fromDegrees($latitude, $longitude);
	public static function fromRadians($latitude, $longitude);
  public function getLatitudeInDegrees();
  public function getLongitudeInDegrees();
  public function getLatitudeInRadians();
  public function getLongitudeInRadians();
	public static function MilesToKilometers($miles);
	public static function KilometersToMiles($km);
}

