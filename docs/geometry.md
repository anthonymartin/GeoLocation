# Geometry

[README](../README.md) · [Architecture](../ARCHITECTURE.md)

Save each PHP example in your application root, beside `vendor/`, after installing the package.

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

## Distance between points

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;

$geopointA = new GeoPoint(40.5187154, -74.4120953);
$geopointB = new GeoPoint(40.65, -73.95);
$distance = $geopointA->distanceTo($geopointB, 'miles'); // approximately 25.8886 miles
```

## Polygon containment

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

These methods use planar ray casting and must not be used across the antimeridian. Containment
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
[the migration guide](../UPGRADE-3.0.md) for the updated signatures.

## Polygon measurements and spherical containment

```php
<?php
require 'vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon;

$polygon = Polygon::fromArray([[0, 0], [0, 1], [1, 1], [1, 0]]);
$areaKm2 = $polygon->area('km');
$perimeterKm = $polygon->perimeter('km');
$center = $polygon->centroid();
$valid = $polygon->isValid();
$errors = $polygon->validationErrors();
$clockwise = $polygon->isClockwise();
$reversed = $polygon->reversed();
$simplified = $polygon->simplify(100); // meters
$copy = Polygon::fromGeoPoints($polygon->getVertices());
$wrapped = Polygon::fromArray([[-10, 170], [-10, -170], [10, -170], [10, 170]]);
$inside = $wrapped->containsSpherical(new GeoPoint(0, 180)); // true
```

`area()` uses oriented spherical excess, summing great-circle triangle solid angles,
and returns the smaller area in km² or mi². `perimeter()` sums haversine edge lengths.
These use a spherical Earth, not an ellipsoid: error varies by latitude and shape,
so **±0.3% is not a universal area guarantee**. The 1° equatorial test cell is about
12,364 km²; a four-corner Colorado approximation is only a rough comparison with
its surveyed border. See the [JPL spherical-polygon discussion](https://ntrs.nasa.gov/citations/20210005701).

`centroid()` is the normalized 3D **vertex mean**, not an area-weighted centroid.
It handles longitude wrapping, can lie outside concave rings, and throws for an
empty ring or a zero resultant. Adding vertices changes the weighting.

`containsSpherical()` is an inclusive winding test for simple rings enclosing less
than a hemisphere, with minor great-circle edges. It handles poles and the date
line and is independent of orientation. Antipodal edges and exactly hemispherical
interiors are ambiguous and throw. Self-intersecting spherical rings are unsupported.
`contains()`, `containsGeoPoint()` and `inPolygon()` retain planar half-open behavior.
The polygon's `boundingBox()` is also planar: do not use it (including SQL/geohash
candidate filters) to bound an opt-in spherical region.

`isValid()` / `validationErrors()` check **planar** rings for distinct vertices,
consecutive duplicates, zero signed area, and non-adjacent segment intersections.
They are O(n²), do not certify spherical validity, and do not run implicitly in
constructors. `isClockwise()` also uses planar longitude/latitude orientation.

`simplify()` applies closed-ring Douglas–Peucker using spherical segment distances.
Tolerance must be finite and nonnegative. It preserves the original when fewer
than three vertices would remain, and leaves incomplete rings and zero tolerance
unchanged. It does not guarantee topology preservation; validate the result when
that matters. `reversed()` and `simplify()` return immutable values.

For large rings, use the variadic constructor, `fromArray()` or `fromGeoPoints()`.
Repeated `withVertex()` calls copy the growing array and cost O(n²) overall.
