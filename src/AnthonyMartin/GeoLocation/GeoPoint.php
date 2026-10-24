<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation;

use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;
use AnthonyMartin\GeoLocation\Exceptions\OutOfBoundsException;

/** Immutable geographic coordinate; all getters return floats. */
readonly class GeoPoint implements Interfaces\GeoPointInterface {
    protected float $radLat;
    protected float $radLon;
    protected float $degLat;
    protected float $degLon;

    public function __construct(mixed $latitude, mixed $longitude, bool $inRadians = false) {
        $latitude = self::normalizeCoordinate($latitude, 'Latitude',
            $inRadians ? Earth::getMINLAT() : -90, $inRadians ? Earth::getMAXLAT() : 90);
        $longitude = self::normalizeCoordinate($longitude, 'Longitude',
            $inRadians ? Earth::getMINLON() : -180, $inRadians ? Earth::getMAXLON() : 180);
        $this->radLat = $inRadians ? $latitude : deg2rad($latitude);
        $this->radLon = $inRadians ? $longitude : deg2rad($longitude);
        $this->degLat = $inRadians ? rad2deg($latitude) : $latitude;
        $this->degLon = $inRadians ? rad2deg($longitude) : $longitude;
    }

    /** Return a new point; the original point and its longitude are unchanged. */
    public function withLatitude(mixed $latitude, bool $inRadians = false): static {
        if ($inRadians) {
            $latitude = rad2deg(self::normalizeCoordinate($latitude, 'Latitude', Earth::getMINLAT(), Earth::getMAXLAT()));
        }
        return new static($latitude, $this->degLon);
    }

    /** Return a new point; the original point and its latitude are unchanged. */
    public function withLongitude(mixed $longitude, bool $inRadians = false): static {
        if ($inRadians) {
            $longitude = rad2deg(self::normalizeCoordinate($longitude, 'Longitude', Earth::getMINLON(), Earth::getMAXLON()));
        }
        return new static($this->degLat, $longitude);
    }

    public function boundingBox(mixed $distance, mixed $unit_of_measurement): BoundingBox {
        return BoundingBox::fromGeoPoint($this, $distance, $unit_of_measurement);
    }

    /** Great-circle distance using a spherical Earth and the haversine formula. */
    public function distanceTo(GeoPoint $geopoint, mixed $unit_of_measurement): float {
        $radius = Earth::getRadius($unit_of_measurement);
        $latitudeTerm = sin(($geopoint->radLat - $this->radLat) / 2);
        $longitudeTerm = sin(($geopoint->radLon - $this->radLon) / 2);
        $haversine = $latitudeTerm * $latitudeTerm +
            cos($this->radLat) * cos($geopoint->radLat) * $longitudeTerm * $longitudeTerm;
        $haversine = max(0.0, min(1.0, $haversine));
        return 2 * atan2(sqrt($haversine), sqrt(1 - $haversine)) * $radius;
    }

    public function inPolygon(Polygon $polygon): bool { return $polygon->containsGeoPoint($this); }
    public function getLatitude(bool $inRadians = false): float { return $inRadians ? $this->radLat : $this->degLat; }
    public function getLongitude(bool $inRadians = false): float { return $inRadians ? $this->radLon : $this->degLon; }
    public function getRadLat(): float { return $this->radLat; }
    public function getRadLon(): float { return $this->radLon; }
    public function getDegLat(): float { return $this->degLat; }
    public function getDegLon(): float { return $this->degLon; }

    protected static function normalizeCoordinate(mixed $coordinate, string $name, float $minimum, float $maximum): float {
        if ((!is_int($coordinate) && !is_float($coordinate) && !is_string($coordinate)) || !is_numeric($coordinate)) {
            throw new InvalidArgumentException($name . ' must be a finite numeric value');
        }
        $coordinate = (float) $coordinate;
        if (!is_finite($coordinate)) {
            throw new InvalidArgumentException($name . ' must be a finite numeric value');
        }
        if ($coordinate < $minimum || $coordinate > $maximum) {
            throw new OutOfBoundsException($name . ' must be between ' . $minimum . ' and ' . $maximum);
        }
        return $coordinate;
    }
}
