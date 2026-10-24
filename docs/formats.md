# Formats

[README](../README.md) · [Architecture](../ARCHITECTURE.md)

Save each PHP example in your application root, beside `vendor/`, after installing the package.

## GeoJSON and WKT

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
