<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation;

class Earth {

  public const float RADIUS_KM = 6371.01;
  public const float RADIUS_MI = 3958.762079;
  /** WGS-84 semi-major axis in meters. */
  public const float WGS84_A = 6378137.0;
  /** WGS-84 flattening. */
  public const float WGS84_F = 1 / 298.257223563;

  /** @deprecated Use the bound getters; writable compatibility properties will be removed in 4.0. */
  public static float $MIN_LAT = -M_PI_2;
  /** @deprecated Use the bound getters; writable compatibility properties will be removed in 4.0. */
  public static float $MAX_LAT = M_PI_2;
  /** @deprecated Use the bound getters; writable compatibility properties will be removed in 4.0. */
  public static float $MIN_LON = -M_PI;
  /** @deprecated Use the bound getters; writable compatibility properties will be removed in 4.0. */
  public static float $MAX_LON = M_PI;

  /** @param string $unit_of_measurement */
  public static function getRadius(mixed $unit_of_measurement): float {
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
    public static function getMINLAT(): float
    {
        return deg2rad(-90);
    }

    /**
     * @return float
     */
    public static function getMAXLAT(): float
    {
        return deg2rad(90);
    }

    /**
     * @return float
     */
    public static function getMINLON(): float
    {
        return deg2rad(-180);
    }

    /**
     * @return float
     */
    public static function getMAXLON(): float
    {
        return deg2rad(180);
    }


}
