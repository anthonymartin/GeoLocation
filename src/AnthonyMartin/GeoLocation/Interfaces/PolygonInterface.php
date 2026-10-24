<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Interfaces;

/** An immutable sequence of perimeter vertices with planar containment. */
interface PolygonInterface extends Region {
    /** @param iterable<GeoPointInterface> $points */
    public static function fromGeoPoints(iterable $points): self;
    public function area(string $unit): float;
    public function perimeter(string $unit): float;
    public function centroid(): GeoPointInterface;
    public function simplify(float $toleranceMeters): self;
    public function isValid(): bool;
    /** @return list<string> */
    public function validationErrors(): array;
    public function isClockwise(): bool;
    public function reversed(): self;
    public function containsSpherical(GeoPointInterface $point): bool;
    public function withVertex(GeoPointInterface $point): self;
    /** @param array<array{int|float|numeric-string, int|float|numeric-string}> $vertices */
    public static function fromArray(array $vertices): self;
    public function containsGeoPoint(GeoPointInterface $point): bool;
    /** @return list<GeoPointInterface> */
    public function getVertices(): array;
    /** @return list<GeoPointInterface> */
    public function getGeoPoints(): array;
}
