# Upgrading from 2.x to 3.0

3.0 is an opt-in major release. Existing 2.x APIs remain supported as-is;
2.1.3 removes the deprecation annotations and supersedes the retirement wording
in 2.1.2. Stay on `^2.0` unless you choose to migrate.
The `2.x` branch retains PHP 5.4 support and receives security and bug fixes
alongside 3.x, with no scheduled end-of-support date.

## PHP and Composer

Before: PHP 5.4 or later, `composer require anthonymartin/geo-location:^2.0`.

After: PHP 8.3 or later:

```bash
composer require anthonymartin/geo-location:^3.0
```

Geometry needs no optional extensions. Install cURL only if using
`GoogleGeocoder`. JSON is part of PHP 8 itself; it is no longer listed as an
optional Composer extension. PHPUnit 12.5 is a development dependency only.

## Legacy GeoLocation class and interface

`GeoLocation` and `Interfaces\GeoLocationInterface` are removed. Before:

```php
$point = GeoLocation::fromDegrees(40.5, -74.4);
$radians = GeoLocation::fromRadians(0.5, -1.0);
$bounds = $point->boundingCoordinates(5, 'km');
$latitude = $point->getLatitudeInDegrees();
```

After:

```php
$point = new GeoPoint(40.5, -74.4);
$radians = new GeoPoint(0.5, -1.0, true);
$box = $point->boundingBox(5, 'km');
$latitude = $point->getLatitude();
```

Use `getLatitude(true)`/`getLongitude(true)` instead of the legacy radian getters.
Replace the two legacy bounds with `getSouthWest()` and `getNorthEast()`.
Distances still use `distanceTo($otherPoint, $unit)` on `GeoPoint`.
For legacy unit conversion helpers, multiply miles by `1.609344`, or divide
kilometers by that value. Calculate an angular radius explicitly as
`$distance / Earth::getRadius($unit)` instead of relying on mutable `getAngular()`
state. Format coordinates explicitly instead of casting the point to a string.
Replace implementations of the removed interface with the relevant modern
interfaces; 3.0 interfaces have native parameter and return types.

## Immutable points

Before:

```php
$point->setDegLat(41);
$point->setDegLon(-73);
$point->setRadLat(0.5);
$point->setRadLon(-1);
```

After:

```php
$point = $point->withLatitude(41)->withLongitude(-73);
$point = $point->withLatitude(0.5, true)->withLongitude(-1, true);
```

Every `with...()` call returns a new point. Existing references, including points
already stored in a polygon or bounding box, remain unchanged. Capture the return
value. These methods retain finite/range validation and throw without changing
the original. Coordinate fields are readonly; setting dynamic properties or
mutating protected fields from a subclass is no longer allowed.

## Coordinate types

Before:

```php
$point = new GeoPoint('40.5', -74);
$point->getLatitude() === '40.5'; // true in 2.x
$point->getLongitude() === -74; // true in 2.x
```

After:

```php
$point = new GeoPoint('40.5', -74);
$point->getLatitude() === 40.5; // true in 3.0
$point->getLongitude() === -74.0; // true in 3.0
```

All `GeoPoint` coordinate getters and aliases return `float`, including radians.
All four `BoundingBox` coordinate getters and its corner points also return floats.
Finite numeric strings remain valid inputs. Update strict comparisons, type-based
logic, and serialization expectations that depended on strings or integers.
Invalid, non-finite, and out-of-range coordinates still throw.

## Box vertex order

Before: `getVertices()` and `getGeoPoints()` returned SW, SE, NW, NE.
After: both return SW, SE, NE, NW, matching `getPerimeter()` and `getPolygon()`.

```php
// Before: index 2 meant north-west.
$northWest = $box->getVertices()[2];
// After: use the named getter; this also works on 2.1.1 and later.
$northWest = $box->getNorthWest();
$ring = $box->getPerimeter();
$polygon = $box->getPolygon();
```

All four named getters retain their meanings, including wrapped boxes. General
polygon containment remains planar and half-open and does not support rings
crossing the antimeridian. Use `BoundingBox::contains()` for inclusive or wrapped
box containment; the new array order does not change those geometric limits.

## Separate Google geocoder

Before:

```php
$point = GeoPoint::fromAddress($address, $apiKey);
$response = GeoLocation::getGeocodeFromGoogle($address, $apiKey);
```

After:

```php
use AnthonyMartin\GeoLocation\GoogleGeocoder;
use AnthonyMartin\GeoLocation\Exceptions\ExceptionInterface;

try {
    $geocoder = new GoogleGeocoder($apiKey);
    $point = $geocoder->geocode($address);
} catch (ExceptionInterface $error) {
    // Handle missing keys, transport failures, or invalid/API error responses.
}
```

`geocode()` is an instance method with signature `geocode(string $address): GeoPoint`.
The constructor requires a string key; an empty key throws `NoApiKeyException`.
There is no raw-response/null geocoding API in 3.0. Callers needing Google's full
response must use a Google API client directly instead of this point-oriented API.
The formerly internal static `GoogleGeocoder` helper is now this public adapter;
its old static `geocode()` and raw `request()` contracts are not retained.

The first result is used, including partial matches. Requests retain URL encoding,
a 5-second connection timeout, and a 10-second total timeout. `ZERO_RESULTS`, HTTP
errors, malformed responses, and transport failures throw descriptive exceptions.
`CurlErrorException` remains an `UnexpectedResponseException`. Missing cURL throws
a library exception when geocoding; installing and using geometry needs no cURL.

## Exceptions

Before: the library's `Exceptions\InvalidArgumentException` extended
`Exceptions\Exception`. After: it extends PHP's `\InvalidArgumentException` and
implements `ExceptionInterface`; `InvalidUnitException` extends it.

```php
// Before: does not catch the new argument-exception family in 3.0.
catch (\AnthonyMartin\GeoLocation\Exceptions\Exception $error) { /* ... */ }
// After: catches all library exception families in both 2.x and 3.0.
catch (\AnthonyMartin\GeoLocation\Exceptions\ExceptionInterface $error) { /* ... */ }
```

Use `\InvalidArgumentException` if you specifically want PHP-compatible argument
errors. Other existing library exceptions keep their inheritance. Native PHP
signature violations raise `TypeError`, which is separate from the library's
validation exceptions.

## Subclasses and interface implementations

Before, methods generally had no native return types. After, overrides and
implementations must match the declared signatures. For example:

```php
readonly class MyPoint extends GeoPoint {
    public function distanceTo(GeoPoint $geopoint, mixed $unit_of_measurement): float {
        return parent::distanceTo($geopoint, $unit_of_measurement);
    }
}
```

`GeoPoint` subclasses must also be `readonly`. Geometry predicates return `bool`,
coordinate/distance getters return `float`, vertex getters return `array`, and
`Polygon::addVertex()` returns `void`. Factory methods return their concrete
geometry classes. Check the interfaces when updating custom implementations.
Constructors remain absent from interfaces, so a `BoundingBox` subclass can still
use a different constructor signature.

## Deliberately unchanged

Before and after: polygons with zero, one, or two vertices return `false` for
containment, without warnings. Malformed vertices still throw. There is no new
minimum-vertex exception to handle. Geometry still uses haversine distances,
requires no BCMath, and preserves the validation and containment fixes from 2.x.
