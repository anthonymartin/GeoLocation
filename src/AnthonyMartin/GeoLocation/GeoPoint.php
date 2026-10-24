<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation;

use AnthonyMartin\GeoLocation\Interfaces\GeoPointInterface;
use AnthonyMartin\GeoLocation\Interfaces\PolygonInterface;
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;
use AnthonyMartin\GeoLocation\Exceptions\OutOfBoundsException;

/** Immutable geographic coordinate; all getters return floats. */
readonly class GeoPoint implements Interfaces\GeoPointInterface {
    protected float $radLat;
    protected float $radLon;
    protected float $degLat;
    protected float $degLon;

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
     * Return a shallow copy with a new latitude, preserving subclass fields.
     * @param int|float|numeric-string $latitude
     */
    public function withLatitude(mixed $latitude, bool $inRadians = false): static {
        $latitude = self::normalizeCoordinate($latitude, 'Latitude',
            $inRadians ? Earth::getMINLAT() : -90, $inRadians ? Earth::getMAXLAT() : 90);
        return $this->copyWithCoordinates(
            $inRadians ? $latitude : deg2rad($latitude), $this->radLon,
            $inRadians ? rad2deg($latitude) : $latitude, $this->degLon
        );
    }

    /**
     * Return a shallow copy with a new longitude, preserving subclass fields.
     * @param int|float|numeric-string $longitude
     */
    public function withLongitude(mixed $longitude, bool $inRadians = false): static {
        $longitude = self::normalizeCoordinate($longitude, 'Longitude',
            $inRadians ? Earth::getMINLON() : -180, $inRadians ? Earth::getMAXLON() : 180);
        return $this->copyWithCoordinates(
            $this->radLat, $inRadians ? $longitude : deg2rad($longitude),
            $this->degLat, $inRadians ? rad2deg($longitude) : $longitude
        );
    }

    /**
     * Copy initialized instance fields without invoking subclass constructors or
     * clone hooks. Object-valued fields are shared, as in a shallow clone.
     * Subclasses with coordinate-dependent metadata may override this hook.
     */
    protected function copyWithCoordinates(float $radLat, float $radLon, float $degLat, float $degLon): static {
        $reflection = new \ReflectionClass($this);
        $copy = $reflection->newInstanceWithoutConstructor();
        $coordinates = compact('radLat', 'radLon', 'degLat', 'degLon');
        $copied = [];
        // Walk each declaring class to include private ancestor fields too.
        do {
            foreach ($reflection->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() !== $reflection->getName() ||
                    $property->isStatic() || !$property->isInitialized($this)) {
                    continue;
                }
                $name = $property->getName();
                $slot = $property->isPrivate() ? $reflection->getName() . '::' . $name : $name;
                if (isset($copied[$slot])) {
                    continue;
                }
                $copied[$slot] = true;
                $value = array_key_exists($name, $coordinates)
                    ? $coordinates[$name] : $property->getValue($this);
                $property->setValue($copy, $value);
            }
            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);
        return $copy;
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
