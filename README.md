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
composer require anthonymartin/geo-location:^2.1
```

The library supports PHP 5.4 and later and requires the cURL and JSON extensions.
BCMath is not required. Distances accept `km`, `kilometers`, `mi`, or `miles`,
case-insensitively. Both `distanceTo()` and `boundingBox()` require an explicit
unit argument; neither has a default unit. Distances use a spherical Earth
approximation.

`new GeoPoint($latitude, $longitude, $inRadians = false)` takes latitude first,
then longitude. By default the values are degrees, within ±90° latitude and
±180° longitude. Pass `true` as the third argument to supply radians instead.
`GeoPoint` accepts finite numeric values and normalizes numeric strings to floats.
`getLatitude()` and `getLongitude()` return degrees; pass `true` to either getter
for radians.

Use [Composer 2.2 LTS](https://getcomposer.org/download/) when installing on
PHP 5.4–7.1.

Examples
========

These examples use the v2.1 API. Save each PHP example in your application's
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
to configure billing, enable the API, and create an API key. `GeoPoint::fromAddress($address, $apiKey = null)`
is static and returns a `GeoPoint`. An API key is required: omitting it throws
`NoApiKeyException`.

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;

$geopoint = GeoPoint::fromAddress('New York, NY 10001', 'google-api-key-goes-here');
$latitude = $geopoint->getLatitude();
$longitude = $geopoint->getLongitude();

```

`fromAddress()` uses the first result returned by Google, including a partial
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

Polygons use planar ray casting: they must not cross the antimeridian and must
have at least three vertices when tested. Boundary behavior is half-open, so
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

`getVertices()` and `getGeoPoints()` retain the 2.0.1 order: SW, SE, NW, NE.
That is not a perimeter; use `getPolygon()` or `getPerimeter()` when building
polygons. Version 2.1.0 changed these indexes unintentionally; 2.1.1 restores them.
For a box that does not cross the antimeridian, pass the returned polygon to
`$geopointA->inPolygon($polygon)`. For inclusive box boundaries or a box crossing
the antimeridian, use `$boundingBox->contains($geopointA)` instead.


Exceptions and legacy API
--------
All library exceptions implement `AnthonyMartin\GeoLocation\Exceptions\ExceptionInterface`:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;

try {
  $point = GeoPoint::fromAddress('New York', 'your-api-key');
} catch (\AnthonyMartin\GeoLocation\Exceptions\ExceptionInterface $error) {
  echo $error->getMessage();
}
```

Invalid units throw `Exceptions\InvalidUnitException`, which extends PHP's
`\InvalidArgumentException`. The other library exception classes extend the
library's `Exceptions\Exception` base. Use `ExceptionInterface` to catch both
families.

The `GeoLocation` class remains available but is deprecated in favor of
`GeoPoint`, `BoundingBox`, and `Polygon`. Its geocoding method returns Google's raw, unvalidated decoded response
(including API error statuses), or `null` on transport/JSON failure; `getAngular()` is zero until a bounding
radius is calculated.

Running Tests
--------
Run these commands from the root of a checkout of this repository.
Full development tests require PHP 7.3 or later. Composer selects PHPUnit 9.6
on older development runtimes and PHPUnit 12.5 on PHP 8.3 or later:

```bash
composer update
composer test
composer test:no-bcmath
```

The committed lock file records the modern development environment. On older
PHP versions, install production dependencies with `composer install --no-dev`
(using Composer 2.2 on PHP 5.4–7.1), then run the standalone runtime checks:

```bash
php tests/legacy-runtime.php
php -n tests/without-bcmath.php
```

CI runs the full suite on PHP 7.3–8.5 and runtime checks on PHP 5.4–7.2.




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
