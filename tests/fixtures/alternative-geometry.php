<?php

declare(strict_types=1);

namespace GeoLocationTests;

use AnthonyMartin\GeoLocation\BoundingBox;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon;
use AnthonyMartin\GeoLocation\Interfaces\BoundingBoxInterface;
use AnthonyMartin\GeoLocation\Interfaces\Geocoder;
use AnthonyMartin\GeoLocation\Interfaces\GeoPointInterface;
use AnthonyMartin\GeoLocation\Interfaces\PolygonInterface;

// Independent implementations: no class extends a library geometry class.
final readonly class OtherPoint implements GeoPointInterface {
    public function __construct(private float $latitude, private float $longitude) {}
    public function getLatitude(bool $inRadians = false): float {
        return $inRadians ? deg2rad($this->latitude) : $this->latitude;
    }
    public function getLongitude(bool $inRadians = false): float {
        return $inRadians ? deg2rad($this->longitude) : $this->longitude;
    }
    public function withLatitude(mixed $latitude, bool $inRadians = false): self {
        $point = (new GeoPoint($this->latitude, $this->longitude))->withLatitude($latitude, $inRadians);
        return new self($point->getLatitude(), $point->getLongitude());
    }
    public function withLongitude(mixed $longitude, bool $inRadians = false): self {
        $point = (new GeoPoint($this->latitude, $this->longitude))->withLongitude($longitude, $inRadians);
        return new self($point->getLatitude(), $point->getLongitude());
    }
    public function inPolygon(PolygonInterface $polygon): bool { return $polygon->containsGeoPoint($this); }
    public function distanceTo(GeoPointInterface $geopoint, mixed $unit_of_measurement): float {
        return (new GeoPoint($this->latitude, $this->longitude))->distanceTo($geopoint, $unit_of_measurement);
    }
    public function boundingBox(mixed $distance, mixed $unit_of_measurement): BoundingBoxInterface {
        return OtherBox::fromGeoPoint($this, $distance, $unit_of_measurement);
    }
}

final readonly class OtherPolygon implements PolygonInterface {
    public function contains(GeoPointInterface $point): bool { return $this->containsGeoPoint($point); }
    public function boundingBox(): BoundingBoxInterface { return (new Polygon(...$this->vertices))->boundingBox(); }
    /** @var list<OtherPoint> */
    private array $vertices;
    public function __construct(GeoPointInterface ...$vertices) {
        $this->vertices = array_values(array_map(
            static fn(GeoPointInterface $p): OtherPoint => new OtherPoint($p->getLatitude(), $p->getLongitude()),
            $vertices
        ));
    }
    public static function fromArray(array $vertices): self {
        return new self(...array_map(static fn(array $pair): OtherPoint =>
            new OtherPoint((float) $pair[0], (float) $pair[1]), $vertices));
    }
    public function withVertex(GeoPointInterface $point): self { return new self(...[...$this->vertices, $point]); }
    public function containsGeoPoint(GeoPointInterface $point): bool { return Polygon::pointInPolygon($point, $this); }
    /** @return list<OtherPoint> */
    public function getVertices(): array { return $this->vertices; }
    /** @return list<OtherPoint> */
    public function getGeoPoints(): array { return $this->vertices; }
}

final readonly class OtherBox implements BoundingBoxInterface {
    public function boundingBox(): BoundingBoxInterface { return $this; }
    public function __construct(private BoundingBox $bounds) {}
    public static function fromGeoPoint(GeoPointInterface $geopoint, mixed $distance, mixed $unit_of_measurement): self {
        return new self(BoundingBox::fromGeoPoint($geopoint, $distance, $unit_of_measurement));
    }
    public function contains(GeoPointInterface $point): bool { return $this->bounds->contains($point); }
    public function getMinLatitude(): float { return $this->bounds->getMinLatitude(); }
    public function getMaxLatitude(): float { return $this->bounds->getMaxLatitude(); }
    public function getMinLongitude(): float { return $this->bounds->getMinLongitude(); }
    public function getMaxLongitude(): float { return $this->bounds->getMaxLongitude(); }
    public function getSouthWest(): OtherPoint { return new OtherPoint($this->getMinLatitude(), $this->getMinLongitude()); }
    public function getSouthEast(): OtherPoint { return new OtherPoint($this->getMinLatitude(), $this->getMaxLongitude()); }
    public function getNorthEast(): OtherPoint { return new OtherPoint($this->getMaxLatitude(), $this->getMaxLongitude()); }
    public function getNorthWest(): OtherPoint { return new OtherPoint($this->getMaxLatitude(), $this->getMinLongitude()); }
    /** @return list<OtherPoint> */
    public function getPerimeter(): array { return [$this->getSouthWest(), $this->getSouthEast(), $this->getNorthEast(), $this->getNorthWest()]; }
    /** @return list<OtherPoint> */
    public function getVertices(): array { return $this->getPerimeter(); }
    /** @return list<OtherPoint> */
    public function getGeoPoints(): array { return $this->getPerimeter(); }
    public function getPolygon(): OtherPolygon { return new OtherPolygon(...$this->getPerimeter()); }
    public function toPolygon(): OtherPolygon { return $this->getPolygon(); }
}

final class OtherGeocoder implements Geocoder {
    /** @return list<\AnthonyMartin\GeoLocation\Interfaces\GeocodingResultInterface> */
    public function geocodeAll(string $address): array {return [new \AnthonyMartin\GeoLocation\Geocoding\GeocodingResult($this->geocode($address),$address)];}
    public function reverse(GeoPointInterface $point): \AnthonyMartin\GeoLocation\Interfaces\GeocodingResultInterface {return new \AnthonyMartin\GeoLocation\Geocoding\GeocodingResult($point,'Office');}
    public function geocode(string $address): OtherPoint { return new OtherPoint(1, 1); }
}
