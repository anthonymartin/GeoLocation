# Geocoding

[README](../README.md) · [Architecture](../ARCHITECTURE.md)

Save each PHP example in your application root, beside `vendor/`, after installing the package.

## Geocoding with Nominatim

Geocoders live in `AnthonyMartin\GeoLocation\Geocoding`. Nominatim needs no API key;
supply your application's User-Agent and a real contact email. For the public OSM
server, read the [usage policy](https://operations.osmfoundation.org/policies/nominatim/):
limit the **whole application to at most one request per second**, cache repeated
lookups, display OpenStreetMap attribution, and do not use it for autocomplete or
systematic/bulk scraping. Choose a self-hosted/commercial endpoint for heavier use.
The `endpoint` constructor option lets you switch services without library changes.
Rate limiting, caching and retries belong in your application's HTTP client wrapper.

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\Geocoding\NominatimGeocoder;

$geocoder = new NominatimGeocoder('YourApplication/1.0', 'you@example.com');
$point = $geocoder->geocode('London');
// For repeated lookups, enforce the limit through your shared client wrapper.
```

Replace the identification values with your own before using the public service.
`geocode()` returns the first `GeoPointInterface`, or throws `AddressNotFoundException`.
`geocodeAll()` returns candidate results, or an empty array; `reverse($point)` returns
an address result for the closest suitable object, which may differ from the requested
coordinate. `GeocodingResult` is an immutable snapshot with `point`, `formattedAddress`,
`components`, `partialMatch`, and `raw` fields and corresponding interface getters.
Keep provider attribution from the raw payload where required.

## Google geocoding

Google requires an API key and [Geocoding API setup](https://developers.google.com/maps/documentation/geocoding/get-api-key).

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\Geocoding\GoogleGeocoder;

$geocoder = new GoogleGeocoder('google-api-key-goes-here', region: 'us', language: 'en');
$point = $geocoder->geocode('New York, NY 10001');
$results = $geocoder->geocodeAll('New York');
$address = $geocoder->reverse($point);
```

Optional `components: ['country' => 'US']` supplies forward-geocoding filters.
Partial matches are exposed in results. HTTP 429 and Google's `OVER_QUERY_LIMIT`
throw `RateLimitedException`. Malformed responses throw `UnexpectedResponseException`;
transport failures remain in the `CurlErrorException` family. All implement the
library's `ExceptionInterface`. The old root `GoogleGeocoder` name is a compatibility
alias deprecated only in 3.x, for removal in 4.0.

## HTTP injection and test doubles

Default geocoding uses native cURL with 5-second connection and 10-second total
timeouts. It needs no Composer runtime dependency. Alternatively inject a PSR-18
client and PSR-17 request factory (`client:` and `requestFactory:`); configure
that client's own timeouts. Guzzle or Symfony's PSR-18 adapter are suitable choices.
Their dependencies are optional Composer suggestions.

The library also supplies `Geocoding\CurlClient`, a PSR-18 adapter for callers
who install `psr/http-client`, `psr/http-factory` and a PSR-17 implementation
such as `nyholm/psr7`. It accepts a response factory and an optional stream factory (inferred when the response
factory implements both interfaces); providers accept the same
factory's request interface. Missing PSR packages produce a clear exception when
loading CurlClient. The dependency-free default uses the underlying native transport.
HTTP 4xx/5xx responses are returned by CurlClient as required by PSR-18; the provider
then maps their statuses. It does not follow redirects automatically.

Applications can depend on `Interfaces\Geocoder` and `GeocodingResultInterface`.
Use the shipped in-memory provider for deterministic tests:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Geocoding\Testing\ArrayGeocoder;

$geocoder = new ArrayGeocoder(['Office' => new GeoPoint(40.5, -74.4)]);
$point = $geocoder->geocode('Office');
$address = $geocoder->reverse($point);
assert($address->getFormattedAddress() === 'Office');
```

## Exceptions

All library exceptions implement `AnthonyMartin\GeoLocation\Exceptions\ExceptionInterface`:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\Geocoding\GoogleGeocoder;

try {
    $point = (new GoogleGeocoder('your-api-key'))->geocode('New York');
} catch (\AnthonyMartin\GeoLocation\Exceptions\ExceptionInterface $error) {
    echo $error->getMessage();
}
```

`Exceptions\InvalidArgumentException` extends PHP's `\InvalidArgumentException`;
`InvalidUnitException` extends the library argument exception. Other library
exception classes retain the `Exceptions\Exception` base. Catch
`ExceptionInterface` for both families. `CurlErrorException` extends
`UnexpectedResponseException`, so geocoding failure catches include transport errors.
Native PHP argument/type errors follow PHP's normal `TypeError` behavior.

The legacy `GeoLocation` class, `GeoLocationInterface`, point setters, and
`GeoPoint::fromAddress()` were removed. Their replacements and native interface
signatures are described in [UPGRADE-3.0.md](../UPGRADE-3.0.md).
