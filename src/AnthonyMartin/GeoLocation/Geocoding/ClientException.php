<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Geocoding;

use AnthonyMartin\GeoLocation\Exceptions\UnexpectedResponseException;
use Psr\Http\Client\ClientExceptionInterface;

/** PSR-18 response/representation failure, distinct from an invalid request. */
final class ClientException extends UnexpectedResponseException implements ClientExceptionInterface {}
