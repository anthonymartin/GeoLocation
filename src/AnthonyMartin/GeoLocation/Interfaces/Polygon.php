<?php

declare(strict_types=1);
namespace AnthonyMartin\GeoLocation\Interfaces;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon as PolygonValue;

interface Polygon {
    public function addVertex(GeoPoint $geopoint): void;
    public static function fromArray(array $vertices): PolygonValue;
    public function containsGeoPoint(GeoPoint $geopoint): bool;
    public function getVertices(): array;
    public function getGeoPoints(): array;
}
