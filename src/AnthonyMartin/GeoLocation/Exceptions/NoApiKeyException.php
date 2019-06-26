<?php

namespace AnthonyMartin\GeoLocation\Exceptions;

class NoApiKeyException extends \Exception
{
    protected $message = "You must use an API key to authenticate each request to Google Maps Geocoding API. For additional information, please refer to http://g.co/dev/maps-no-account";
}