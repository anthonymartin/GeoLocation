# Upgrading from 2.x to 3.0

3.0 is optional. **2.x APIs remain supported as-is**, on PHP 5.4+, with security
and bug fixes alongside 3.x and no scheduled end-of-support date. Version 2.1.3
removes the API deprecation annotations and supersedes 2.1.2's retirement wording.
Applications on `^2.0` continue to receive 2.x releases.

3.0 requires PHP 8.3+:

```bash
composer require anthonymartin/geo-location:^3.0
```

Geometry has no optional extension or runtime Composer dependency. Geocoding uses
cURL by default, or your PSR-18 client and PSR-17 request factory. JSON is built into
supported PHP versions. All examples below run from your application root beside
`vendor/`; substitute real credentials before making provider requests.

## Replacement map

| 2.x API or behavior | 3.0 replacement |
| --- | --- |
| `GeoLocation`, `GeoLocationInterface` | `GeoPoint`, `BoundingBox`, `Polygon` and their interfaces |
| `GeoLocation::fromDegrees($lat, $lon)` | `new GeoPoint($lat, $lon)` |
| `GeoLocation::fromRadians($lat, $lon)` | `new GeoPoint($lat, $lon, true)` |
| `boundingCoordinates($distance, $unit)` | `boundingBox($distance, $unit)`; get named corners from the box |
| `getLatitudeInDegrees()` / `getLongitudeInDegrees()` | `getLatitude()` / `getLongitude()` |
| `getLatitudeInRadians()` / `getLongitudeInRadians()` | `getLatitude(true)` / `getLongitude(true)` |
| Point setters (`setDegLat`, `setDegLon`, `setRadLat`, `setRadLon`) | Assign the result of `withLatitude()` / `withLongitude()` |
| `Polygon::addVertex($point)` | Assign the result of `withVertex($point)` |
| `Interfaces\Polygon` | `Interfaces\PolygonInterface` |
| `GeoPoint::fromAddress($address, $apiKey)` | `(new Geocoding\GoogleGeocoder($apiKey))->geocode($address)` |
| `GeoLocation::getGeocodeFromGoogle()` raw response/null | Provider result objects or a dedicated Google API client |
| Box `getVertices()` / `getGeoPoints()` order SW, SE, NW, NE | Perimeter order SW, SE, NE, NW; prefer named getters |
| Getters preserve input strings/integers | All coordinate getters return floats |
| Library `InvalidArgumentException` extends library `Exception` | Extends PHP `\InvalidArgumentException`; catch `ExceptionInterface` across families |

The old static geocoder helper's `geocode()` and `request()` contracts are removed.
The new geocoder is an instance service. Legacy conversion helpers and mutable
`getAngular()` state are removed: multiply miles by `1.609344`, or divide kilometers
by it; compute an angular radius as `$distance / Earth::getRadius($unit)`.
Format coordinates explicitly instead of casting a point to a string.

## Immutable points and polygons

`GeoPoint` and `Polygon` are **final readonly**. Replace subclasses with composition
or implement their interfaces. Each replacement runs the normal validating
constructor; there is no reflection copying. Keep application-specific state in
another object.

```php
<?php
require 'vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon;

$original = new GeoPoint('40.5', -74);
$moved = $original->withLatitude(41)->withLongitude(-73);
$radians = $moved->withLatitude(0.5, true)->withLongitude(-1, true);
$latitude = $original->getLatitude(); // float 40.5, not string '40.5'
$polygon = Polygon::fromGeoPoints([$original, $moved, $radians]);
$extended = $polygon->withVertex(new GeoPoint(42, -72));
// $original and $polygon are unchanged.
```

Inputs still accept finite numeric strings within ±90° latitude and ±180° longitude.
Update strict comparisons or serialization assumptions that depended on preserved
input types. Constructors and polygon replacement methods snapshot coordinates
from alternative point implementations. Arrays returned by getters cannot mutate
stored geometry. Use a bulk factory/constructor for large polygons; repeated
`withVertex()` copies the growing array.

## Box order and containment

```php
<?php
require 'vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;

$point = new GeoPoint(40.5, -74.4);
$box = $point->boundingBox(5, 'km');
$northWest = $box->getNorthWest(); // index 3 in the new perimeter order
$ring = $box->getPerimeter();
$polygon = $box->getPolygon();
$inside = $box->contains($point);
```

Named corners retain their meanings. Boxes include boundaries and support wrapped
longitude ranges. Polygon `contains()`, `containsGeoPoint()` and point `inPolygon()`
remain planar and half-open; incomplete rings return false. The new
`containsSpherical()` is opt-in and has [separate geometric limits](docs/geometry.md).
A polygon's envelope also remains planar. Do not use that envelope to filter an
opt-in spherical polygon. Malformed vertices and invalid coordinates still throw.

## Geocoding and exceptions

Use the canonical `AnthonyMartin\GeoLocation\Geocoding` namespace:

```php
<?php
require 'vendor/autoload.php';

use AnthonyMartin\GeoLocation\Geocoding\GoogleGeocoder;
use AnthonyMartin\GeoLocation\Exceptions\ExceptionInterface;

try {
    $geocoder = new GoogleGeocoder('your-api-key');
    $point = $geocoder->geocode('New York');
    $results = $geocoder->geocodeAll('New York');
    $address = $geocoder->reverse($point);
    $providerFields = $address->getRaw();
} catch (ExceptionInterface $error) {
    echo $error->getMessage();
}
```

`geocode(string $address): GeoPointInterface` returns the first result, including
partial matches, or throws `AddressNotFoundException`. `geocodeAll()` returns a
list of `GeocodingResultInterface` objects, or an empty list. `reverse()` returns
one result. `getRaw()` preserves that provider **result's** fields, not Google's
complete top-level HTTP response. Use a dedicated API client if you need that full
envelope. Nominatim and in-memory test providers implement the same contracts;
see [Geocoding](docs/geocoding.md).

Native cURL retains URL encoding and 5-second connection/10-second total timeouts.
Injected clients own their timeout policy. Missing cURL fails only when the default
transport is used. `CurlErrorException` still extends `UnexpectedResponseException`.
`RateLimitedException` represents HTTP 429/Google `OVER_QUERY_LIMIT`.

All library exception families implement `ExceptionInterface`. Catching only the
library `Exceptions\Exception` no longer catches argument errors. The library
`InvalidUnitException` extends its `InvalidArgumentException`, which extends PHP's
native argument exception. Native PHP signature errors remain `TypeError`.

## Interface implementations and named arguments

Constructors are absent from interfaces. Factories return `self`; geometry results
use `GeoPointInterface`, `BoundingBoxInterface` or `PolygonInterface`, and concrete
implementations may use covariant returns. `BoundingBox` remains subclassable.
Implementations must honor the documented finite-coordinate and collection contracts.

`GeoPointInterface` includes degree/radian getters, coordinate replacements,
`distanceTo()`, `inPolygon()` and `boundingBox()`. Both `BoundingBoxInterface` and
`PolygonInterface` extend `Region` (`contains()` and `boundingBox()`). Box interfaces
include named corners and perimeter getters. Polygon interfaces include immutable
replacement, bulk factories, measurements, validation, orientation, simplification
and spherical containment. Match every signature in the shipped interfaces when
updating custom implementations.

`Geocoder` requires `geocode()`, `geocodeAll()` and `reverse()`; results implement
`GeocodingResultInterface`. Use `Geocoding\Testing\ArrayGeocoder` in application tests.

Parameter names match across interfaces and implementations. For example,
`$box->contains(point: $point)` and `$polygon->containsGeoPoint(point: $point)` use
`point:`, while `distanceTo()` uses `geopoint:` and `unit_of_measurement:`. Native
return types are enforced; collection types are documented with PHPDoc and checked
by PHPStan. Numeric inputs that need descriptive validation retain native `mixed`.

## Two 3.x deprecations

`Earth::$MIN_LAT`, `$MAX_LAT`, `$MIN_LON`, `$MAX_LON` are initialized, typed float
radian bounds. Use `Earth::getMINLAT()` and the corresponding bound getters; the
writable properties are scheduled for removal in 4.0 and do not control calculations.

The root `AnthonyMartin\GeoLocation\GoogleGeocoder` is a compatibility alias to
`Geocoding\GoogleGeocoder`, also scheduled for removal in 4.0. Use the canonical name
in new code. These deprecations apply only to 3.x; existing 2.x APIs remain supported.
