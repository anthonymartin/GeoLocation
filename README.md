# GeoLocation for PHP

Geographic coordinates, great-circle distances, bounding boxes, polygons, and
Google geocoding for PHP 8.3+. Geometry works without optional extensions.

## Installation and compatibility

```bash
composer require anthonymartin/geo-location:^3.0
```

The library requires PHP 8.3 or later. Geometry needs neither cURL nor BCMath.
Only `GoogleGeocoder` needs cURL; JSON is built into supported PHP versions.
Upgrading from 2.x? Read [UPGRADE-3.0.md](UPGRADE-3.0.md) first. Applications on
`^2.0` stay on 2.x, which retains PHP 5.4 support and receives security and bug
fixes alongside 3.x, with no scheduled end-of-support date.

## Quick start

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;

$original = new GeoPoint(40.5, -74.4);
$moved = $original->withLatitude(41.0)->withLongitude(-73.5);
$distanceKm = $original->distanceTo($moved, 'km');
$nearby = $original->boundingBox(5, 'km')->contains($moved);
// $original remains (40.5, -74.4); $moved is (41.0, -73.5).
```

## Coordinates and units

Distances accept `km`, `kilometers`, `mi`, or `miles`, case-insensitively.
`distanceTo()` and `boundingBox()` require an explicit unit and use a spherical
Earth approximation.

`new GeoPoint($latitude, $longitude, $inRadians = false)` takes latitude first,
then longitude. Degrees must be within ±90° latitude and ±180° longitude; pass
`true` as the third argument for radians. Finite integers, floats, and numeric
strings are accepted and normalized to floats. All coordinate getters return
floats. `getLatitude()` and `getLongitude()` return degrees; pass `true` for radians.

`GeoPoint` is final and readonly. To change a coordinate, assign
the new point returned by `withLatitude()` or `withLongitude()`; the original
point remains unchanged. Both methods accept `true` as their second argument
for radian input.

## API at a glance

`Number` below means a finite integer, float, or numeric string. Coordinates
must also fall within the latitude/longitude bounds. Invalid inputs throw library
exceptions; accepting numeric strings does not mean accepting booleans or null.

| API | Returns | Notes |
| --- | --- | --- |
| `new GeoPoint(Number $latitude, Number $longitude, bool $inRadians = false)` | `GeoPoint` | Latitude first; immutable. |
| `$point->getLatitude(bool $inRadians = false)` / `getLongitude(...)` | `float` | Degrees by default. |
| `$point->withLatitude(Number $latitude, bool $inRadians = false)` / `withLongitude(...)` | `GeoPoint` | New validated point; original unchanged. |
| `$point->distanceTo(GeoPointInterface $geopoint, string $unit_of_measurement)` | `float` | Explicit unit; spherical approximation. |
| `$point->boundingBox(Number $distance, string $unit_of_measurement)` | `BoundingBox` | Nonnegative search radius. |
| `$box->contains(GeoPointInterface $point)` | `bool` | Inclusive boundaries; supports antimeridian crossing. |
| `Polygon::fromArray(array $vertices)` | `Polygon` | `[latitude, longitude]` pairs in perimeter order. |
| `$point->inPolygon(PolygonInterface $polygon)` / `$polygon->containsGeoPoint(GeoPointInterface $point)` | `bool` | Planar, half-open boundaries. |
| `$box->getPerimeter()` / `$box->getVertices()` / `$polygon->getVertices()` | `list<GeoPoint>` | Box order: SW, SE, NE, NW. |
| `$box->getPolygon()` / `$box->toPolygon()` | `Polygon` | New immutable polygon. |
| `$polygon->withVertex(GeoPointInterface $point)` | `Polygon` | New polygon; appends a coordinate snapshot. |
| `$geocoder->geocode(string $address)` | `GeoPointInterface` | Google returns `GeoPoint`; other providers may return their own point type. |

Interface names in this table live in `AnthonyMartin\GeoLocation\Interfaces`.
The table describes accepted input types. Numeric inputs and units retain native
`mixed` parameters so invalid values receive descriptive library exceptions.
Public methods declare native return types; collection element types and numeric
inputs have precise PHPDoc annotations checked by PHPStan.

## Examples

These examples use the 3.0 API. Save each PHP example in your application's
root directory, beside `vendor/`, after installing the package. Replace the
API-key placeholder in geocoding examples with your Google API key.

### Distance between points

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;

$geopointA = new GeoPoint(40.5187154, -74.4120953);
$geopointB = new GeoPoint(40.65, -73.95);
$distance = $geopointA->distanceTo($geopointB, 'miles'); // approximately 25.8886 miles
```

### Google geocoding

Follow Google's [Geocoding API setup guide](https://developers.google.com/maps/documentation/geocoding/get-api-key)
to configure billing, enable the API, and create an API key. `GoogleGeocoder::geocode(string $address): GeoPoint`
returns a `GeoPoint`. Construct the geocoder with your API key; an empty key
throws `NoApiKeyException`.

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GoogleGeocoder;

$geocoder = new GoogleGeocoder('google-api-key-goes-here');
$geopoint = $geocoder->geocode('New York, NY 10001');
$latitude = $geopoint->getLatitude();
$longitude = $geopoint->getLongitude();

```

`geocode()` uses the first result returned by Google, including a partial
match. It does not disambiguate addresses. Requests have a 5-second connection
timeout and a 10-second total timeout. Transport failures, HTTP errors, malformed
responses, and Google statuses such as `ZERO_RESULTS` produce descriptive
exceptions.

### Other providers and test doubles

In 3.0, application services can depend on
`AnthonyMartin\GeoLocation\Interfaces\Geocoder` instead of a particular provider.
`GoogleGeocoder` implements this interface. Supply another implementation for a
different provider, or a local test double:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Interfaces\Geocoder;

$geocoder = new class implements Geocoder {
    public function geocode(string $address): GeoPoint {
        return new GeoPoint(40.5, -74.4); // A deterministic test fixture.
    }
};
$point = $geocoder->geocode('Office');
```

Providers return a point or throw. The interface does not impose constructor
arguments, provider-specific configuration, or a raw response format.

### Bounding box coordinates

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;

$geopointA = new GeoPoint(40.5187154, -74.4120953);
$boundingBox = $geopointA->boundingBox(3, 'miles');
$maxLatitude = $boundingBox->getMaxLatitude();
$maxLongitude = $boundingBox->getMaxLongitude();
$minLatitude = $boundingBox->getMinLatitude();
$minLongitude = $boundingBox->getMinLongitude();

```

`boundingBox($distance, $unit_of_measurement)` returns a `BoundingBox`. The
distance is a nonnegative search radius, not the box's width or height. The box
encloses that radius and can also contain points farther away; use `distanceTo()`
when you need to check the distance itself. Box coordinate getters return degrees.

### Bounding box containment

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;

$geopointA = new GeoPoint(40.5187154, -74.4120953);
$boundingBox = $geopointA->boundingBox(3, 'miles');
if ($boundingBox->contains($geopointA)) {
    echo "GeoPoint is in the bounding box!";
}
```

`contains()` includes every edge and corner, handles boxes crossing ±180°,
and includes the center of a zero-distance box. Use it for bounding-box checks.

### Polygon containment

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon;

$geopointA = new GeoPoint(40.5187154, -74.4120953);
$polygon = Polygon::fromArray([
    [40.50, -74.45],
    [40.50, -74.35],
    [40.55, -74.35],
    [40.55, -74.45],
]);
if ($geopointA->inPolygon($polygon)) {
    echo "GeoPoint is in Polygon!";
}
```

`Polygon::fromArray(array $vertices)` accepts `[latitude, longitude]` pairs in
degrees, ordered around the perimeter, and returns a `Polygon`. The closing edge
is added implicitly. `$point->inPolygon($polygon)` and
`$polygon->containsGeoPoint($point)` both return a boolean.

Polygons use planar ray casting and must not cross the antimeridian. Containment
returns `false` for fewer than three vertices. Boundary behavior is half-open, so
some edges are inside and others are outside. Use `BoundingBox::contains()` for
inclusive box boundaries.

`Polygon` is a final readonly value. Replace mutation with `withVertex()` and
capture the returned polygon. The original polygon and its vertices stay unchanged.
The constructor accepts points directly: `new Polygon($pointA, $pointB, $pointC)`.
Coordinates from alternative point implementations are snapshotted on input,
so later changes to those objects cannot change the polygon.

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon;

$empty = new Polygon();
$triangle = $empty->withVertex(new GeoPoint(0, 0))
    ->withVertex(new GeoPoint(0, 4))
    ->withVertex(new GeoPoint(4, 0));
// $empty still has no vertices; $triangle has three.
```

### Box corners and polygons

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;

$geopointA = new GeoPoint(40.5187154, -74.4120953);
$boundingBox = $geopointA->boundingBox(5, 'mi');
$polygon = $boundingBox->toPolygon();
```

`toPolygon()` is an alias of `getPolygon()`. Both build a proper ring in
SW, SE, NE, NW order. `getPerimeter()` returns the four `GeoPoint` corners in
that same ring order. For a single corner, use `getSouthWest()`, `getSouthEast()`,
`getNorthWest()`, or `getNorthEast()`.

`getVertices()` and `getGeoPoints()` also return SW, SE, NE, NW perimeter order
in 3.0. Use named getters when selecting individual corners; 2.x used a different
index order (SW, SE, NW, NE).
For a box that does not cross the antimeridian, pass the returned polygon to
`$geopointA->inPolygon($polygon)`. For inclusive box boundaries or a box crossing
the antimeridian, use `$boundingBox->contains($geopointA)` instead.

## Interfaces and alternative implementations

Geometry accepts `GeoPointInterface` and `PolygonInterface` inputs. A point from
an alternative geocoder can be passed directly to `distanceTo()`,
`BoundingBox::fromGeoPoint()`, `contains()`, and polygon construction.

Interface factories return `self`; geometry results use `GeoPointInterface`,
`PolygonInterface`, and `BoundingBoxInterface`. Implementations can return their
own types without extending this library's concrete classes. The bundled classes
retain precise concrete return types where appropriate. Interface constructors
are unconstrained. `PolygonInterface` replaces the old `Interfaces\Polygon` name.

All providers must honor finite coordinate/range and collection contracts.
`GeoPointInterface` does not force storage immutability; the bundled `GeoPoint`
is readonly, and `Polygon` snapshots alternative inputs. See
[the migration guide](UPGRADE-3.0.md) for the updated signatures.

## Exceptions

All library exceptions implement `AnthonyMartin\GeoLocation\Exceptions\ExceptionInterface`:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GoogleGeocoder;

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
signatures are described in [UPGRADE-3.0.md](UPGRADE-3.0.md).

## Development

Run these commands from the root of a repository checkout on PHP 8.3 or later:

```bash
composer install
composer test
composer test:no-bcmath
composer test:readme
composer analyse
```

CI tests PHP 8.3, 8.4, and 8.5, including geometry installation without cURL.
PHPStan checks all library source at level 8 without a baseline. README examples
are also executed with mocked geocoding.
Geocoding tests use local response fixtures and do not require an API key.
The `2.x` branch retains its separate PHP 5.4–8.5 compatibility suite.

## Credits and license

This is a collection of PHP classes written by Anthony Martin. Some of GeoLocation.php was derived from Java code that was originally published at
<a href="http://JanMatuschek.de/LatitudeLongitudeBoundingCoordinates">
http://JanMatuschek.de/LatitudeLongitudeBoundingCoordinates</a>.<br />

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, TITLE AND NON-INFRINGEMENT. IN NO EVENT SHALL THE COPYRIGHT HOLDERS OR ANYONE DISTRIBUTING THE SOFTWARE BE LIABLE FOR ANY DAMAGES OR OTHER LIABILITY, WHETHER IN CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Copyright (c) 2019 Anthony Martin

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

Contributors: see [ARCHITECTURE.md](ARCHITECTURE.md) for layer boundaries and `composer lint`.

### Regions

`BoundingBox`, `Polygon`, and `Circle` implement `Interfaces\Region` with
`contains(GeoPointInterface $point)` and `boundingBox()`. Boxes and circles include
their boundaries; polygons keep planar, half-open edges. Polygon envelopes use
vertex minima/maxima and do not represent antimeridian-crossing regions. An empty
polygon has no envelope and throws `InvalidArgumentException`.

```php
<?php
require 'vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Interfaces\Region;

$center = new GeoPoint(40.7, -74.0);
$circle = $center->circle(5, 'km');
$isInside = static fn(Region $region): bool => $region->contains($center);
assert($isInside($circle));
assert($isInside($circle->boundingBox()));
assert($isInside($circle->boundingBox()->getPolygon()));
```

A circle tests actual spherical distance. Its bounding box also includes corners
outside that radius. Circle centers are immutable snapshots of the supplied point.

`Earth::RADIUS_KM` and `RADIUS_MI` are typed spherical radii; `WGS84_A` (meters)
and `WGS84_F` describe the reference ellipsoid. Use `Earth::getMINLAT()` /
`getMAXLAT()` / `getMINLON()` / `getMAXLON()` for radian bounds. The old writable
bound properties are now typed floats and deprecated for removal in 4.0. This
deprecation applies only to 3.x; the supported 2.x APIs are unchanged.

### Spherical navigation

`bearingTo()` and `finalBearingTo()` return degrees clockwise from north in
[0, 360). `destination()` accepts a nonnegative distance and any finite bearing;
`midpointTo()` and `interpolate($to, $fraction)` follow the shortest great-circle
arc. Fractions must be in [0, 1]. Coincident and antipodal bearings are undefined
and throw `InvalidArgumentException`; antipodal interpolation also throws.
At the north pole initial bearing is south (180°), at the south pole north (0°).
A destination from a pole uses the starting longitude to orient its local tangent.
These are spherical calculations using the same Earth radii as `distanceTo()`.

```php
<?php
require 'vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;

$cambridge = new GeoPoint(52.205, 0.119);
$paris = new GeoPoint(48.857, 2.351);
$bearing = $cambridge->bearingTo($paris); // approximately 156.167 degrees
$destination = $cambridge->destination(100, $bearing, 'km');
$halfway = $cambridge->midpointTo($paris);
```

The tests use [Chris Veness's spherical reference](https://www.movable-type.co.uk/scripts/latlong.html)
and 500 deterministic random round trips checked to within one millimeter.

### Accuracy and ellipsoidal geodesics

Spherical distances are fast but can differ from ellipsoidal distances by roughly
0.5%. Opt in to `Geodesy\Vincenty` for WGS-84 distances and initial/final bearings:

```php
<?php
require 'vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Geodesy\Vincenty;

$solver = new Vincenty();
$start = new GeoPoint(52.205, 0.119);
$end = new GeoPoint(48.857, 2.351);
$result = $solver->inverse($start, $end);
$kilometers = $result->distance('km');
$destination = $solver->direct($start, $result->distanceMeters, $result->initialBearing);
```

`Geodesic` is a swappable solver contract. `Ellipsoid` defaults to WGS-84; use
`new Ellipsoid(...Ellipsoid::GRS80)` or supply semi-major axis (meters) and
flattening. `GeodesicResult` contains meters and bearings in [0, 360); coincident
endpoints use zero for all three values. Miles convert using exactly 1609.344 m.

Vincenty has a 200-iteration cap and throws `GeodesicConvergenceException` when
it cannot converge. Its inverse also rejects points within **2° of the antipode**:
series truncation can spoil bearing accuracy there even if iteration converges.
There is no silent spherical fallback. Direct solutions remain available for those
routes. Karney's solver is not included in this release. The checked WGS-84 sample
meets 1 mm distance and 1e-8° bearing tolerances in the supported inverse domain;
all 1,000 direct cases meet 1 mm. Accuracy is not guaranteed for extreme flattening.

References: [Vincenty's equations](https://geodesy.noaa.gov/PUBS_LIB/inverse.pdf)
and [Karney's published test data](https://sourceforge.net/projects/geographiclib/files/testdata/).

### SQL store locator

`Sql\NearbyQuery` builds SQL and bindings for MySQL, PostgreSQL and SQLite. It
never opens a connection. Boxes are exact, circles add a haversine radius test,
and polygon/custom-region filters return **bounding-box candidates only**;
`requiresPostFiltering()` tells you to apply the region's `contains()` in PHP.
Column names are validated and quoted, and coordinates are always parameters.

This complete example uses an in-memory SQLite database:

```php
<?php
require 'vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Sql\NearbyQuery;
use AnthonyMartin\GeoLocation\Sql\SqliteDialect;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE places (id INTEGER PRIMARY KEY, name TEXT, lat REAL, lng REAL)');
$insert = $pdo->prepare('INSERT INTO places VALUES (?, ?, ?, ?)');
$insert->execute([1, 'Downtown', 40.71, -74.01]);
$insert->execute([2, 'Far away', 41.0, -75.0]);
$center = new GeoPoint(40.7, -74.0);
$query = new NearbyQuery(new SqliteDialect(), named: true);
$distance = $query->distanceExpression($center, 'km');
$nearby = $query->within($center->circle(5, 'km'));
$statement = $pdo->prepare('SELECT *, '.$distance->sql().' AS distance_km FROM places WHERE '
    .$nearby->sql().' ORDER BY distance_km LIMIT 10');
$statement->execute(array_merge($distance->bindings(), $nearby->bindings()));
$places = $statement->fetchAll(PDO::FETCH_ASSOC);
assert(count($places) === 1);
```

Use `within($circle->boundingBox())` for a box-only query. Positional `?` bindings
are the default; merge their arrays in SQL occurrence order. Named bindings are
unique across calls on one builder; give separate builders different
`parameterPrefix` values if combining their fragments in one statement. A named
placeholder occurs only once, so native PDO prepares work without emulation.
Both ±180° spellings of the antimeridian are handled.

SQLite must have [math functions enabled](https://www.sqlite.org/lang_mathfunc.html)
(or equivalent functions registered by your application). Set `atan2: true` to
use `2*ATAN2` rather than `2*ASIN`; it still requires SIN, COS, RADIANS, POWER and
SQRT. All variants clamp rounding errors before inverse trigonometry. Store valid,
finite coordinates in your database. Optional integration tests use
`GEOLOCATION_MYSQL_DSN` / `GEOLOCATION_POSTGRES_DSN`, with
`GEOLOCATION_DB_USER` and `GEOLOCATION_DB_PASSWORD` when needed.

### GeoJSON and WKT

`Format\GeoJson` and `Format\Wkt` convert points and outer polygon rings without
I/O. Both use **longitude first**; `new GeoPoint()` takes latitude first.
GeoJSON `encode()` returns an array; `encodeToString()` returns JSON text.
Decoders accept only 2D points and one closed polygon ring. They reject holes,
3D positions, unsupported geometry types and malformed data with library exceptions.
Feature wrappers are deliberately unsupported; pass their geometry explicitly.

```php
<?php
require 'vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Format\GeoJson;
use AnthonyMartin\GeoLocation\Format\Wkt;

$point = new GeoPoint(40.7, -74.0);
$geojson = GeoJson::encodeToString($point->circle(5, 'km'), segments: 64);
$decoded = GeoJson::decode($geojson);
$wkt = Wkt::encode($point); // POINT (-74.0 40.7)
```

Return `$geojson` with `Content-Type: application/geo+json`; in a Leaflet client,
pass the parsed response to `L.geoJSON(data).addTo(map)`.

Boxes become closed five-position rings. Circles become polygon approximations
(3–10,000 segments; default 64); GeoJSON includes a bounding box. Wrapped boxes
and circles are cut at ±180° and can emit **MultiPolygon** for correct map display;
their RFC 7946 `bbox` may have west greater than east. Decoding MultiPolygon is
not supported, so those outputs are intended for map clients. Polar circle caps
include the pole. Zero-radius circles become points. Circle approximation requires
a radius smaller than a hemisphere; unrepresentably small rings throw.

Plain polygons retain their planar edges and min/max envelopes. WKT supports only
POINT and single-ring POLYGON, so wrapped shapes requiring multiple parts throw.
WKT has no embedded CRS/SRID support. Neither codec silently drops holes or altitude.
