<?php

declare(strict_types=1);
namespace AnthonyMartin\GeoLocation\Interfaces;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon as PolygonValue;

interface Polygon {
    public function addVertex(GeoPoint $geopoint): void;
    /** @param array<array{int|float|numeric-string, int|float|numeric-string}> $vertices */
    public static function fromArray(array $vertices): PolygonValue;
    public function containsGeoPoint(GeoPoint $p): bool;
    /** @return list<GeoPoint> */
    public function getVertices(): array;
    /** @return list<GeoPoint> */
    public function getGeoPoints(): array;
}
