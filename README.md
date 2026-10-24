GeoLocation for PHP
===========

GeoLocation for PHP offers convenient and easy to use methods for geocoding, geolocation and geometry functions in PHP.

Features include:

1. Retrieve bounding box coordinates. Just define a coordinate and the size of your bounding box.
2. Calculate distances between geopoints/coordinates
3. Solve point in polygon problems (identify whether a given point is within the bounds of a polygon)



Installation and compatibility
========

```bash
composer require anthonymartin/geo-location:^3.0
```

The library requires PHP 8.3 or later. Geometry needs neither cURL nor BCMath.
Only `GoogleGeocoder` needs cURL; JSON is built into supported PHP versions.
Upgrading from 2.x? Read [UPGRADE-3.0.md](UPGRADE-3.0.md) first. Applications on
`^2.0` stay on 2.x, which retains PHP 5.4 support and receives security and bug
fixes alongside 3.x, with no scheduled end-of-support date.

Distances accept `km`, `kilometers`, `mi`, or `miles`, case-insensitively.
`distanceTo()` and `boundingBox()` require an explicit unit and use a spherical
Earth approximation.

`new GeoPoint($latitude, $longitude, $inRadians = false)` takes latitude first,
then longitude. Degrees must be within ±90° latitude and ±180° longitude; pass
`true` as the third argument for radians. Finite integers, floats, and numeric
strings are accepted and normalized to floats. All coordinate getters return
floats. `getLatitude()` and `getLongitude()` return degrees; pass `true` for radians.

`GeoPoint` is immutable and declared `readonly`. To change a coordinate, assign
the new point returned by `withLatitude()` or `withLongitude()`; the original
point remains unchanged. Both methods accept `true` as their second argument
for radian input.

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;

$original = new GeoPoint(40.5, -74.4);
$moved = $original->withLatitude(41.0)->withLongitude(-73.5);
// $original remains (40.5, -74.4); $moved is (41.0, -73.5).
```

Examples
========

These examples use the v3.0 API. Save each PHP example in your application's
root directory, beside `vendor/`, after installing the package. Replace the
API-key placeholder in geocoding examples with your Google API key.

Get distance between two points:
--------------------------------------------------------
```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;

$geopointA = new GeoPoint(40.5187154, -74.4120953);
$geopointB = new GeoPoint(40.65, -73.95);
$distance = $geopointA->distanceTo($geopointB, 'miles'); // approximately 25.8886 miles
```


Get latitude and longitude from address or location
--------------------------------------------------------
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

Get bounding box coordinates
--------------------------------------------------------
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

Check whether a point is inside a bounding box
--------------------------------------------------------
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

How to find if coordinates/geopoint are in a polygon.
------------------------
```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon;

$geopointA = new GeoPoint(40.5187154, -74.4120953);
$polygon = Polygon::fromArray(array(
    [40.50, -74.45],
    [40.50, -74.35],
    [40.55, -74.35],
    [40.55, -74.45]
));
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
inclusive box boundaries. Vertices can be added incrementally with `addVertex()`.

Transform bounding box coordinates into a polygon
-------
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


Exceptions
--------
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

Running Tests
--------
Run these commands from the root of a repository checkout on PHP 8.3 or later:

```bash
composer install
composer test
composer test:no-bcmath
```

CI tests PHP 8.3, 8.4, and 8.5, including geometry installation without cURL.
Geocoding tests use local response fixtures and do not require an API key.
The `2.x` branch retains its separate PHP 5.4–8.5 compatibility suite.

Credits and Legal
--------------------------------------------------------
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
