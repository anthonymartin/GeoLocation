<?php

namespace AnthonyMartin\GeoLocation;

class Earth {

  const RADIUS_KM = 6371.01;
  const RADIUS_MI = 3958.762079;
  public static $MIN_LAT;  // -PI/2
  public static $MAX_LAT;  //  PI/2
  public static $MIN_LON;  // -PI
  public static $MAX_LON;  //  PI

  public static function getRadius($unit_of_measurement) {
    if (is_string($unit_of_measurement)) {
      $unit = strtolower($unit_of_measurement);
      if ($unit === 'miles' || $unit === 'mi') {
        return self::RADIUS_MI;
      }
      if ($unit === 'kilometers' || $unit === 'km') {
        return self::RADIUS_KM;
      }
    }
    throw new Exceptions\InvalidUnitException('You must supply a valid unit of measurement: km, kilometers, mi or miles.');
  }

    /**
     * @return float
     */
    public static function getMINLAT()
    {
        return deg2rad(-90);
    }

    /**
     * @return float
     */
    public static function getMAXLAT()
    {
        return deg2rad(90);
    }

    /**
     * @return float
     */
    public static function getMINLON()
    {
        return deg2rad(-180);
    }

    /**
     * @return float
     */
    public static function getMAXLON()
    {
        return deg2rad(180);
    }


}
