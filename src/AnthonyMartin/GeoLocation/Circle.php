<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation;

use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;
use AnthonyMartin\GeoLocation\Interfaces\BoundingBoxInterface;
use AnthonyMartin\GeoLocation\Interfaces\GeoPointInterface;
use AnthonyMartin\GeoLocation\Interfaces\Region;

/** An inclusive spherical radius around a snapshotted center. */
final readonly class Circle implements Region {
    private GeoPoint $center;

    /** Radius uses the same km/mi aliases as Earth::getRadius(). */
    public function __construct(GeoPointInterface $center, private float $radius, private string $unit) {
        Earth::getRadius($unit);
        if (!is_finite($radius) || $radius < 0) {
            throw new InvalidArgumentException('Circle radius must be finite and nonnegative.');
        }
        $this->center = new GeoPoint($center->getLatitude(), $center->getLongitude());
    }

    /** Return the immutable center snapshot. */
    public function getCenter(): GeoPointInterface { return $this->center; }
    /** Return the radius in the constructor's unit. */
    public function getRadius(): float { return $this->radius; }
    /** Return the constructor's validated unit. */
    public function getUnit(): string { return $this->unit; }
    /** Include points whose great-circle distance is at most the radius. */
    public function contains(GeoPointInterface $point): bool {
        return $this->center->distanceTo($point, $this->unit) <= $this->radius;
    }
    /** Enclose the circle, including wrapped longitudes and poles. */
    public function boundingBox(): BoundingBoxInterface {
        return $this->center->boundingBox($this->radius, $this->unit);
    }
}
