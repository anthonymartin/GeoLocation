<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Interfaces;

interface BoundingBoxInterface extends Region {
    /**
     * @param int|float|numeric-string $distance
     * @param string $unit_of_measurement
     */
    public static function fromGeoPoint(GeoPointInterface $geopoint, mixed $distance, mixed $unit_of_measurement): self;
    public function contains(GeoPointInterface $point): bool;
    public function getMinLatitude(): float;
    public function getMinLongitude(): float;
    public function getMaxLatitude(): float;
    public function getMaxLongitude(): float;
    /** @return list<GeoPointInterface> */
    public function getVertices(): array;
    /** @return list<GeoPointInterface> */
    public function getGeoPoints(): array;
    /** @return list<GeoPointInterface> */
    public function getPerimeter(): array;
    public function getSouthWest(): GeoPointInterface;
    public function getSouthEast(): GeoPointInterface;
    public function getNorthWest(): GeoPointInterface;
    public function getNorthEast(): GeoPointInterface;
    public function getPolygon(): PolygonInterface;
    public function toPolygon(): PolygonInterface;
}
