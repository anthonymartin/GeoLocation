<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Geodesy;

use AnthonyMartin\GeoLocation\Interfaces\GeoPointInterface;

/** Swappable ellipsoidal solver; implementations may report non-convergence. */
interface Geodesic {
    /** Solve distance and departure/arrival bearings between coordinates. */
    public function inverse(GeoPointInterface $a, GeoPointInterface $b): GeodesicResult;
    /** Travel a nonnegative distance in meters along a bearing in degrees. */
    public function direct(GeoPointInterface $from, float $distanceMeters, float $bearing): GeoPointInterface;
}
