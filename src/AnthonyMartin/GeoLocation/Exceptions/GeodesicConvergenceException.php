<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Exceptions;

/** The chosen geodesic solver cannot produce a reliable solution. */
class GeodesicConvergenceException extends Exception {}
