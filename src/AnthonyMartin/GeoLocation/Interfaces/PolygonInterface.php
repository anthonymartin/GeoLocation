<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Interfaces;

/** An immutable sequence of perimeter vertices with planar containment. */
interface PolygonInterface {
    public function withVertex(GeoPointInterface $point): self;
    /** @param array<array{int|float|numeric-string, int|float|numeric-string}> $vertices */
    public static function fromArray(array $vertices): self;
    public function containsGeoPoint(GeoPointInterface $point): bool;
    /** @return list<GeoPointInterface> */
    public function getVertices(): array;
    /** @return list<GeoPointInterface> */
    public function getGeoPoints(): array;
}
