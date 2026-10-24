<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Geocoding;

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Exceptions\{ExceptionInterface, UnexpectedResponseException};

/** @internal Normalize untrusted provider coordinates into a validated point. */
final class ProviderPoint {
    /** Reject malformed fields before crossing the numeric coordinate API boundary. */
    public static function from(mixed $latitude,mixed $longitude): GeoPoint {
        if(!is_numeric($latitude)||!is_numeric($longitude)) {throw new UnexpectedResponseException('Geocoding result has invalid coordinates.');}
        try {return new GeoPoint($latitude,$longitude);}
        catch(ExceptionInterface $error) {throw new UnexpectedResponseException('Geocoding result has invalid coordinates.',0,$error);}
    }
}
