<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Interfaces;

/** A provider-independent address-to-point service; failures throw. */
interface Geocoder {
    public function geocode(string $address): GeoPointInterface;
}
