<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Interfaces;

/** Replaceable provider supporting forward candidates and reverse lookup. */
interface Geocoder {
    /** Return the first candidate's point; no match throws AddressNotFoundException. */
    public function geocode(string $address): GeoPointInterface;
    /** @return list<GeocodingResultInterface> Candidates, or [] when no address matches. */
    public function geocodeAll(string $address): array;
    /** Resolve the nearest address or throw AddressNotFoundException. */
    public function reverse(GeoPointInterface $point): GeocodingResultInterface;
}
