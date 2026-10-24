<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Exceptions;

/** A well-formed lookup returned no matching address. */
class AddressNotFoundException extends UnexpectedResponseException {}
