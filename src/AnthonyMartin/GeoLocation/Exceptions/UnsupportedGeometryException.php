<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Exceptions;

/** A geometry cannot be represented by this codec's supported subset. */
class UnsupportedGeometryException extends InvalidArgumentException {}
