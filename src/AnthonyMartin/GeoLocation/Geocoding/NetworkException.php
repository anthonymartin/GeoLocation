<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Geocoding;

use AnthonyMartin\GeoLocation\Exceptions\CurlErrorException;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/** PSR-18 network failure, retaining the attempted request. */
final class NetworkException extends CurlErrorException implements NetworkExceptionInterface {
    /** Wrap the original native transport failure. */
    public function __construct(string $message,private readonly RequestInterface $request,?\Throwable $previous=null) {parent::__construct($message,0,$previous);}
    /** Return the attempted HTTP request. */
    public function getRequest(): RequestInterface {return $this->request;}
}
