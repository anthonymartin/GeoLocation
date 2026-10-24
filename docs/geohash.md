# Geohash

[README](../README.md) · [Architecture](../ARCHITECTURE.md)

Save each PHP example in your application root, beside `vendor/`, after installing the package.

## Geohash indexing

`Index\Geohash` supplies 1–12-character base32 hashes, full-precision cell centers,
cell boxes, and eight neighbors. It has no native extension dependency. H3 is outside
this package's scope. Neighbor keys are `N`, `NE`, `E`, `SE`, `S`, `SW`, `W`, `NW`;
they follow conventional geohash grid wrapping at the antimeridian and between the
top and bottom rows at the poles.

```php
<?php
require 'vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Index\Geohash;

$point = new GeoPoint(42.6, -5.6);
$hash = Geohash::encode($point, 5); // ezs42
$center = Geohash::decode($hash);
$cell = Geohash::decodeBox($hash);
$neighbors = Geohash::neighbors($hash);
$circle = $point->circle(2, 'km');
$precision = Geohash::precisionForRadius(2, 'km');
$hashes = Geohash::cover($circle, $precision);
```

`precisionForRadius()` chooses the **finest** precision whose equatorial cell spans
at least the diameter, falling back to 1 for large radii and 12 for zero. This is a
starting heuristic; high-latitude cells are narrower. Use `cover()` for coverage.
It returns exactly the cells intersecting a closed box, including boundary-touching
cells and ±180° aliases; circles and polygons use their bounding envelopes, so apply
`contains()` to candidates afterward. The default `maxCells: 100000` throws before
allocating an oversized cover; reduce precision or explicitly raise the limit.

Store hashes at the chosen precision, or compare prefixes of longer hashes. Bind
`$hashes` as parameters in your own `IN` clause; keep values out of SQL text. The SQL
and Index layers remain independent.
