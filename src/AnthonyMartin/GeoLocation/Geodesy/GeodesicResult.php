<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Geodesy;

use AnthonyMartin\GeoLocation\Earth;
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;

/** Ellipsoidal inverse result; bearings are clockwise from north in degrees. */
final readonly class GeodesicResult {
    /** Coincident endpoints use distance=0 and the convention bearing=0. */
    public function __construct(public float $distanceMeters, public float $initialBearing, public float $finalBearing) {
        if (!is_finite($distanceMeters) || $distanceMeters < 0 ||
            !is_finite($initialBearing) || $initialBearing < 0 || $initialBearing >= 360 ||
            !is_finite($finalBearing) || $finalBearing < 0 || $finalBearing >= 360) {
            throw new InvalidArgumentException('Invalid geodesic distance or bearing.');
        }
    }

    /** Convert to km/kilometers or mi/miles (one international mile = 1609.344 m). */
    public function distance(string $unit): float {
        return $this->distanceMeters / (Earth::getRadius($unit) === Earth::RADIUS_KM ? 1000 : 1609.344);
    }
}
