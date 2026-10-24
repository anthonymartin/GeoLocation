<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Interfaces;

/** A geographic region with shape-specific boundary semantics. */
interface Region {
    /** Test membership; polygons retain their documented half-open edges. */
    public function contains(GeoPointInterface $point): bool;
    /** Return a latitude/longitude envelope for this region. */
    public function boundingBox(): BoundingBoxInterface;
}
