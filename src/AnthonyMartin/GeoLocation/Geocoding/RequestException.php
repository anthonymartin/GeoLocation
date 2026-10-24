<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Geocoding;

use AnthonyMartin\GeoLocation\Exceptions\UnexpectedResponseException;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;

/** PSR-18 invalid or unreadable request. */
final class RequestException extends UnexpectedResponseException implements RequestExceptionInterface {
    /** Retain the invalid request and optional stream error. */
    public function __construct(string $message,private readonly RequestInterface $request,?\Throwable $previous=null) {parent::__construct($message,0,$previous);}
    /** Return the rejected HTTP request. */
    public function getRequest(): RequestInterface {return $this->request;}
}
