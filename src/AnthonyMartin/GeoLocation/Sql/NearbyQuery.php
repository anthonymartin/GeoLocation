<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Sql;

use AnthonyMartin\GeoLocation\{Circle, Earth, GeoPoint};
use AnthonyMartin\GeoLocation\Interfaces\{BoundingBoxInterface, GeoPointInterface, Region};
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;

/** Builds parameterized spatial expressions without connections or I/O. */
final class NearbyQuery {
    private readonly string $latitude;
    private readonly string $longitude;
    private int $sequence = 0;

    /**
     * Named parameters are unique across calls on this builder. Use a different
     * parameterPrefix for each builder whose fragments share a SQL statement.
     * atan2=true selects 2*ATAN2 instead of 2*ASIN for the spherical distance.
     */
    public function __construct(private readonly Dialect $dialect, string $latColumn = 'lat', string $lonColumn = 'lng',
        private readonly bool $named = false, private readonly string $parameterPrefix = 'geo', private readonly bool $atan2 = false) {
        $this->latitude = $dialect->quoteIdentifier($latColumn);
        $this->longitude = $dialect->quoteIdentifier($lonColumn);
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $parameterPrefix)) {
            throw new InvalidArgumentException('SQL parameter prefix must be a valid identifier.');
        }
    }

    /** Exact for boxes/circles; polygon and custom regions return envelope candidates. */
    public function within(Region $region): SqlFragment {
        $bindings = [];
        $box = $region->boundingBox();
        $south = $this->parameter($box->getMinLatitude(),$bindings);
        $north = $this->parameter($box->getMaxLatitude(),$bindings);
        $west = $this->parameter($box->getMinLongitude(),$bindings);
        $east = $this->parameter($box->getMaxLongitude(),$bindings);
        $lon = $this->longitude;
        $longitude = $box->getMinLongitude() <= $box->getMaxLongitude()
            ? "$lon BETWEEN $west AND $east" : "$lon >= $west OR $lon <= $east";
        // +180 and -180 denote the same meridian, including zero-width boxes.
        if ($box->getMinLongitude() === -180.0 || $box->getMaxLongitude() === -180.0) { $longitude .= " OR $lon = 180"; }
        if ($box->getMinLongitude() === 180.0 || $box->getMaxLongitude() === 180.0) { $longitude .= " OR $lon = -180"; }
        $sql = "({$this->latitude} BETWEEN $south AND $north) AND ($longitude)";
        if ($region instanceof Circle) {
            $distance = $this->distanceSql($region->getCenter(),$region->getUnit(),$bindings);
            $radius = $this->parameter($region->getRadius(),$bindings);
            $sql .= " AND ($distance <= $radius)";
        }
        return new SqlFragment("($sql)",$bindings,!($region instanceof Circle || $region instanceof BoundingBoxInterface));
    }

    /** Build a spherical distance expression with a parameterized center. */
    public function distanceExpression(GeoPointInterface $point, string $unit): SqlFragment {
        $bindings=[];
        $expression=$this->distanceSql($point,$unit,$bindings);
        return new SqlFragment($expression,$bindings);
    }

    /** @param array<int|string, float> $bindings */
    private function distanceSql(GeoPointInterface $point, string $unit, array &$bindings): string {
        $center = new GeoPoint($point->getLatitude(),$point->getLongitude());
        $radius = $this->parameter(Earth::getRadius($unit),$bindings);
        $haversine = $this->haversine($center,$bindings);
        if ($this->atan2) {
            $second = $this->haversine($center,$bindings);
            return "(2 * $radius * ATAN2(SQRT($haversine), SQRT(1.0 - $second)))";
        }
        return "(2 * $radius * ASIN(SQRT($haversine)))";
    }

    /** @param array<int|string, float> $bindings */
    private function haversine(GeoPointInterface $point, array &$bindings): string {
        $lat = $this->parameter($point->getLatitude(),$bindings);
        $cosLat = $this->parameter($point->getLatitude(),$bindings);
        $lon = $this->parameter($point->getLongitude(),$bindings);
        return $this->dialect->clamp("POWER(SIN(RADIANS({$this->latitude} - $lat) / 2), 2) + COS(RADIANS({$this->latitude})) * COS(RADIANS($cosLat)) * POWER(SIN(RADIANS({$this->longitude} - $lon) / 2), 2)");
    }

    /** @param array<int|string, float> $bindings */
    private function parameter(float $value, array &$bindings): string {
        if (!is_finite($value)) { throw new InvalidArgumentException('SQL bindings must be finite numbers.'); }
        if ($this->named) {
            $name=$this->parameterPrefix.'_'.(++$this->sequence);
            $bindings[$name]=$value;
            $placeholder=':'.$name;
        } else { $bindings[]=$value; $placeholder='?'; }
        // PDO commonly binds floats as strings. Numeric coercion is essential
        // when SQLite compares an expression (no column affinity) with a radius.
        return "($placeholder + 0.0)";
    }
}
