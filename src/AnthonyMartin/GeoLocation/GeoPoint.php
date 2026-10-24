<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation;

use AnthonyMartin\GeoLocation\Interfaces\GeoPointInterface;
use AnthonyMartin\GeoLocation\Interfaces\PolygonInterface;
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;
use AnthonyMartin\GeoLocation\Exceptions\OutOfBoundsException;

/** Immutable geographic coordinate; all getters return floats. */
final readonly class GeoPoint implements GeoPointInterface {
    /** Create an inclusive spherical circle centered on this point. */
    public function circle(float $radius, string $unit): Circle { return new Circle($this, $radius, $unit); }

    private float $radLat;
    private float $radLon;
    private float $degLat;
    private float $degLon;

    /**
     * @param int|float|numeric-string $latitude
     * @param int|float|numeric-string $longitude
     */
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

    /**
     * Return a validated point with a new latitude; the original is unchanged.
     * @param int|float|numeric-string $latitude
     */
    public function withLatitude(mixed $latitude, bool $inRadians = false): self {
        $latitude = self::normalizeCoordinate($latitude, 'Latitude',
            $inRadians ? Earth::getMINLAT() : -90, $inRadians ? Earth::getMAXLAT() : 90);
        return new self($inRadians ? rad2deg($latitude) : $latitude, $this->degLon);
    }

    /**
     * Return a validated point with a new longitude; the original is unchanged.
     * @param int|float|numeric-string $longitude
     */
    public function withLongitude(mixed $longitude, bool $inRadians = false): self {
        $longitude = self::normalizeCoordinate($longitude, 'Longitude',
            $inRadians ? Earth::getMINLON() : -180, $inRadians ? Earth::getMAXLON() : 180);
        return new self($this->degLat, $inRadians ? rad2deg($longitude) : $longitude);
    }

    /**
     * @param int|float|numeric-string $distance
     * @param string $unit_of_measurement
     */
    public function boundingBox(mixed $distance, mixed $unit_of_measurement): BoundingBox {
        return BoundingBox::fromGeoPoint($this, $distance, $unit_of_measurement);
    }

    /**
     * Great-circle distance using a spherical Earth and the haversine formula.
     * @param string $unit_of_measurement
     */
    public function distanceTo(GeoPointInterface $geopoint, mixed $unit_of_measurement): float {
        $radius = Earth::getRadius($unit_of_measurement);
        $latitudeTerm = sin(($geopoint->getLatitude(true) - $this->radLat) / 2);
        $longitudeTerm = sin(($geopoint->getLongitude(true) - $this->radLon) / 2);
        $haversine = $latitudeTerm * $latitudeTerm +
            cos($this->radLat) * cos($geopoint->getLatitude(true)) * $longitudeTerm * $longitudeTerm;
        $haversine = max(0.0, min(1.0, $haversine));
        return 2 * atan2(sqrt($haversine), sqrt(1 - $haversine)) * $radius;
    }

    public function inPolygon(PolygonInterface $polygon): bool { return $polygon->containsGeoPoint($this); }
    public function getLatitude(bool $inRadians = false): float { return $inRadians ? $this->radLat : $this->degLat; }
    public function getLongitude(bool $inRadians = false): float { return $inRadians ? $this->radLon : $this->degLon; }
    public function getRadLat(): float { return $this->radLat; }
    public function getRadLon(): float { return $this->radLon; }
    public function getDegLat(): float { return $this->degLat; }
    public function getDegLon(): float { return $this->degLon; }

    /** Initial great-circle bearing in degrees clockwise from north, in [0, 360). */
    public function bearingTo(GeoPointInterface $to): float {
        if ($this->distanceTo($to, 'km') < 1e-12 || $this->isAntipodeOf($to, 1e-6)) {
            throw new InvalidArgumentException('Coincident or antipodal points have no unique bearing.');
        }
        if ($this->degLat === 90.0) { return 180.0; }
        if ($this->degLat === -90.0) { return 0.0; }
        $latitude = $to->getLatitude(true);
        $delta = $to->getLongitude(true) - $this->radLon;
        return self::normalizeBearing(rad2deg(atan2(
            sin($delta) * cos($latitude),
            cos($this->radLat) * sin($latitude) - sin($this->radLat) * cos($latitude) * cos($delta)
        )));
    }

    /** Arrival bearing in degrees clockwise from north, in [0, 360). */
    public function finalBearingTo(GeoPointInterface $to): float {
        $end = new self($to->getLatitude(), $to->getLongitude());
        return self::normalizeBearing($end->bearingTo($this) + 180);
    }

    /** Travel a finite nonnegative spherical distance along an initial bearing. */
    public function destination(float $distance, float $bearing, string $unit): GeoPointInterface {
        $radius = Earth::getRadius($unit);
        if (!is_finite($distance) || $distance < 0 || !is_finite($bearing)) {
            throw new InvalidArgumentException('Distance must be finite and nonnegative; bearing must be finite.');
        }
        if ($distance === 0.0) { return new self($this->degLat, $this->degLon); }
        $angle = fmod($distance / $radius, 2 * M_PI);
        $heading = deg2rad(self::normalizeBearing($bearing));
        // Rotate the position toward the local north/east tangent. Unlike the
        // longitude division formula, this remains well-conditioned at a pole.
        $sinLat = sin($this->radLat); $cosLat = cos($this->radLat);
        $sinLon = sin($this->radLon); $cosLon = cos($this->radLon);
        $north = sin($angle) * cos($heading);
        $east = sin($angle) * sin($heading);
        $x = cos($angle) * $cosLat * $cosLon - $north * $sinLat * $cosLon - $east * $sinLon;
        $y = cos($angle) * $cosLat * $sinLon - $north * $sinLat * $sinLon + $east * $cosLon;
        $z = cos($angle) * $sinLat + $north * $cosLat;
        return new self(rad2deg(atan2($z, hypot($x, $y))), rad2deg(atan2($y, $x)));
    }

    /** Return the great-circle halfway point; antipodal endpoints are undefined. */
    public function midpointTo(GeoPointInterface $to): GeoPointInterface { return $this->interpolate($to, 0.5); }

    /** Interpolate a fraction in [0, 1] along the shortest spherical arc. */
    public function interpolate(GeoPointInterface $to, float $fraction): GeoPointInterface {
        if (!is_finite($fraction) || $fraction < 0 || $fraction > 1) {
            throw new InvalidArgumentException('Interpolation fraction must be finite and between 0 and 1.');
        }
        $end = new self($to->getLatitude(), $to->getLongitude());
        if ($this->isAntipodeOf($end, 1e-6)) {
            throw new InvalidArgumentException('Antipodal points have no unique shortest arc.');
        }
        if ($fraction === 0.0) { return new self($this->degLat, $this->degLon); }
        if ($fraction === 1.0) { return $end; }
        $distance = $this->distanceTo($end, 'km');
        if ($distance < 1e-12) { return new self($this->degLat, $this->degLon); }
        // Cartesian slerp also handles routes whose starting point is a pole.
        $a = [cos($this->radLat) * cos($this->radLon), cos($this->radLat) * sin($this->radLon), sin($this->radLat)];
        $b = [cos($end->radLat) * cos($end->radLon), cos($end->radLat) * sin($end->radLon), sin($end->radLat)];
        $cross = [$a[1]*$b[2]-$a[2]*$b[1], $a[2]*$b[0]-$a[0]*$b[2], $a[0]*$b[1]-$a[1]*$b[0]];
        $angle = atan2(sqrt($cross[0]**2 + $cross[1]**2 + $cross[2]**2), $a[0]*$b[0]+$a[1]*$b[1]+$a[2]*$b[2]);
        $left = sin((1 - $fraction) * $angle) / sin($angle);
        $right = sin($fraction * $angle) / sin($angle);
        $x = $left*$a[0] + $right*$b[0]; $y = $left*$a[1] + $right*$b[1]; $z = $left*$a[2] + $right*$b[2];
        return new self(rad2deg(atan2($z, hypot($x, $y))), rad2deg(atan2($y, $x)));
    }

    /** Test proximity to the opposite point on the sphere, in meters. */
    public function isAntipodeOf(GeoPointInterface $other, float $toleranceMeters = 1.0): bool {
        if (!is_finite($toleranceMeters) || $toleranceMeters < 0) {
            throw new InvalidArgumentException('Antipodal tolerance must be finite and nonnegative.');
        }
        $longitude = $this->degLon >= 0 ? $this->degLon - 180 : $this->degLon + 180;
        return (new self(-$this->degLat, $longitude))->distanceTo($other, 'km') * 1000 <= $toleranceMeters;
    }

    private static function normalizeBearing(float $bearing): float {
        return fmod(fmod($bearing, 360.0) + 360.0, 360.0);
    }

    private static function normalizeCoordinate(mixed $coordinate, string $name, float $minimum, float $maximum): float {
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
