<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Interfaces;

/** Provider-independent geocoding result contract, without transport dependencies. */
interface GeocodingResultInterface {
    /** The resolved geographic point. */
    public function getPoint(): GeoPointInterface;
    /** The provider's human-readable address. */
    public function getFormattedAddress(): string;
    /** @return array<array-key,mixed> Provider-specific address components. */
    public function getComponents(): array;
    /** Whether the provider reported a partial match. */
    public function isPartialMatch(): bool;
    /** @return array<array-key,mixed> Original result payload. */
    public function getRaw(): array;
}
