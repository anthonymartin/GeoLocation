# Geodesy

[README](../README.md) · [Architecture](../ARCHITECTURE.md)

Save each PHP example in your application root, beside `vendor/`, after installing the package.

## Spherical navigation

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

## Accuracy and ellipsoidal geodesics

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
