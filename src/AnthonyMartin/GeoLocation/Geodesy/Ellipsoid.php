<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Geodesy;

use AnthonyMartin\GeoLocation\Earth;
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;

/** Oblate reference ellipsoid, with axes measured in meters. */
final readonly class Ellipsoid {
    /** @var array{float, float} Semi-major axis and flattening, for new Ellipsoid(...Ellipsoid::WGS84). */
    public const array WGS84 = [Earth::WGS84_A, Earth::WGS84_F];
    /** @var array{float, float} GRS 1980 parameters. */
    public const array GRS80 = [6378137.0, 1 / 298.257222101];
    public float $b;

    /** Construct an oblate ellipsoid; f=0 is a sphere. */
    public function __construct(public float $a = Earth::WGS84_A, public float $f = Earth::WGS84_F) {
        if (!is_finite($a) || $a <= 0 || !is_finite($f) || $f < 0 || $f >= 1) {
            throw new InvalidArgumentException('Ellipsoid requires a positive finite axis and flattening in [0, 1).');
        }
        $this->b = $a * (1 - $f);
    }
}
