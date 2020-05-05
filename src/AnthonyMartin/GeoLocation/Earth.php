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
		$u = $unit_of_measurement;
		if($u == 'miles' || $u == 'mi')
			return $radius = self::RADIUS_MI;
		elseif($u == 'kilometers' || $u == 'km')
			return $radius = self::RADIUS_KM;

		else throw new \InvalidArgumentException('You must supply a valid unit of measurement');
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
