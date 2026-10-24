<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Exceptions;

/** The provider returned HTTP 429 or an API quota-limit status. */
class RateLimitedException extends UnexpectedResponseException {}
