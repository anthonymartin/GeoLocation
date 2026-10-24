# Regions

[README](../README.md) · [Architecture](../ARCHITECTURE.md)

Save each PHP example in your application root, beside `vendor/`, after installing the package.

## Regions

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

## Bounding box coordinates

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

## Bounding box containment

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

## Box corners and polygons

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
