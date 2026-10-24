# GeoLocation for PHP

Typed, immutable geographic points and polygons for PHP 8.3+: distances, regions,
ellipsoidal geodesics, SQL proximity filters, GeoJSON/WKT, geohashes and geocoding.
Geometry is pure PHP, with no runtime Composer dependencies or optional extensions.

```bash
composer require anthonymartin/geo-location:^3.0
```

**2.x remains supported as-is**, including PHP 5.4, with security and bug fixes
and no scheduled end-of-support date. `^2.0` stays on 2.x. Moving to 3.0 is optional;
read the [migration guide](UPGRADE-3.0.md) before changing your constraint.

## Five examples

Save each example in your application's root directory, beside `vendor/`.
Coordinates are **latitude, longitude** in degrees. Units are explicit: `km`,
`kilometers`, `mi` or `miles`, case-insensitively. Numeric coordinate strings are
accepted; getters return floats. Invalid or non-finite coordinates throw.

### Distance and immutable coordinates

```php
<?php
require 'vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;

$start = new GeoPoint(52.205, 0.119);
$end = new GeoPoint(48.857, 2.351);
$kilometers = $start->distanceTo($end, 'km'); // about 404.3 km
$moved = $start->withLatitude(53); // $start is unchanged
```

### Bearings and destinations

```php
<?php
require 'vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;

$start = new GeoPoint(52.205, 0.119);
$destination = $start->destination(10, 90, 'km'); // 10 km east
$bearing = $start->bearingTo($destination);
$midpoint = $start->midpointTo($destination);
```

### Bounding boxes

```php
<?php
require 'vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;

$center = new GeoPoint(40.7, -74.0);
$box = $center->boundingBox(5, 'km');
$inside = $box->contains(new GeoPoint(40.71, -74.01));
$southWest = $box->getSouthWest();
$corners = $box->getPerimeter(); // SW, SE, NE, NW
```

### Circles and regions

```php
<?php
require 'vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Interfaces\Region;

$circle = (new GeoPoint(40.7, -74.0))->circle(5, 'km');
$check = static fn(Region $region): bool => $region->contains(new GeoPoint(40.71, -74.01));
$inside = $check($circle);
$envelope = $circle->boundingBox();
```

### Polygons

```php
<?php
require 'vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon;

$polygon = Polygon::fromArray([[0, 0], [0, 1], [1, 1], [1, 0]]);
$inside = $polygon->contains(new GeoPoint(0.5, 0.5));
$areaKm2 = $polygon->area('km');
$perimeterKm = $polygon->perimeter('km');
$reversed = $polygon->reversed(); // $polygon is unchanged
```

Point distances use a sphere. Boxes and circles include their boundaries; boxes
handle the antimeridian. Polygon `contains()` is planar with half-open boundaries.
Use `containsSpherical()` explicitly for minor great-circle rings crossing the date
line or enclosing a pole. See the guides for geometric limits and accuracy.

## Guides

| Feature | Guide |
| --- | --- |
| Coordinates, immutable polygons, area, centroid, simplification, validation | [Geometry](docs/geometry.md) |
| Bounding boxes, circles, interchangeable region contracts | [Regions](docs/regions.md) |
| Bearings, interpolation, WGS-84 Vincenty solver and accuracy limits | [Geodesy](docs/geodesy.md) |
| Parameterized MySQL, PostgreSQL and SQLite proximity queries | [SQL](docs/sql.md) |
| GeoJSON and WKT codecs, coordinate order and unsupported shapes | [Formats](docs/formats.md) |
| Google, Nominatim, reverse geocoding, PSR-18 HTTP and test doubles | [Geocoding](docs/geocoding.md) |
| Encoding, neighbors and bounded region coverage | [Geohashes](docs/geohash.md) |
| Reproducible measurements | [Benchmarks](docs/benchmarks.md) |

Geocoding uses native cURL or an injected PSR-18 client. Credentials, provider
policies, caching and rate limits are described in the geocoding guide.

Contributors: read [Architecture](ARCHITECTURE.md) and [Development](docs/development.md).
CI runs PHP 8.3–8.5, the documentation examples, PHPStan max and dependency/purity
checks. See [CHANGELOG.md](CHANGELOG.md) for release history.

MIT licensed; see [LICENSE](LICENSE). Created by Anthony Martin. Some original
geometry code derives from [Jan Philip Matuschek's bounding-coordinate work](http://JanMatuschek.de/LatitudeLongitudeBoundingCoordinates).
