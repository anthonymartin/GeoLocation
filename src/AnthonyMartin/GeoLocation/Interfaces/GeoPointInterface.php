<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Interfaces;

/** A finite geographic coordinate, independent of its storage implementation. */
interface GeoPointInterface {
    public function getLatitude(bool $inRadians = false): float;
    public function getLongitude(bool $inRadians = false): float;
    /** @param int|float|numeric-string $latitude */
    public function withLatitude(mixed $latitude, bool $inRadians = false): self;
    /** @param int|float|numeric-string $longitude */
    public function withLongitude(mixed $longitude, bool $inRadians = false): self;
    public function inPolygon(PolygonInterface $polygon): bool;
    /** @param string $unit_of_measurement */
    public function distanceTo(GeoPointInterface $geopoint, mixed $unit_of_measurement): float;
    /**
     * @param int|float|numeric-string $distance
     * @param string $unit_of_measurement
     */
    public function boundingBox(mixed $distance, mixed $unit_of_measurement): BoundingBoxInterface;
}
