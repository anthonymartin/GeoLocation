GeoLocation for PHP
===========

GeoLocation for PHP offers convenient and easy to use methods for geocoding, geolocation and geometry functions in PHP.

Repository history: this project was previously hosted at
[`anthonymartin/GeoLocation.php`](https://github.com/anthonymartin/GeoLocation.php).
Its current canonical repository is
[`anthonymartin/GeoLocation`](https://github.com/anthonymartin/GeoLocation).
Both URLs identify the same GitHub repository (ID `6956914`); the Composer
package remains `anthonymartin/geo-location`.

Features include:

1. Retrieve bounding box coordinates. Just define a coordinate and the size of your bounding box.
2. calculate distances between geopoints/coordinates
3. Solve point in polygon problems (identify whether a given point is within the bounds of a polygon)



Installation and compatibility
========

```bash
composer require anthonymartin/geo-location:^2.1
```

The library supports PHP 5.4 and later and requires the cURL and JSON extensions.
BCMath is not required. Distances accept `km`, `kilometers`, `mi`, or `miles`,
case-insensitively. Coordinates must be finite numeric values within latitude
±90° and longitude ±180°; numeric strings are normalized to floats.

Examples
========

Get distance between two points:
--------------------------------------------------------
```php
<?php

use AnthonyMartin\GeoLocation\GeoPoint;

$geopointA = new GeoPoint(40.5187154, -74.4120953);
$geopointB = new GeoPoint(40.65, -73.95);
$distance = $geopointA->distanceTo($geopointB, 'miles');
```


Get latitude and longitude from address or location
--------------------------------------------------------
In order to use this method, you'll need to register at [Google Cloud Console](https://console.cloud.google.com) and enable the [Geocoding API](https://console.cloud.google.com/google/maps-apis/apis/geocoding-backend.googleapis.com)

```php
<?php
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

use AnthonyMartin\GeoLocation\GeoPoint;

$geopointA = new GeoPoint(40.5187154, -74.4120953);
$boundingBox = $geopointA->boundingBox(3, 'miles');
$boundingBox->getMaxLatitude();
$boundingBox->getMaxLongitude();
$boundingBox->getMinLatitude();
$boundingBox->getMinLongitude();

```

Check whether a point is inside a bounding box
--------------------------------------------------------
```php
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

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon;

$geopointA = new GeoPoint(40.5187154, -74.4120953);
$polygon = Polygon::fromArray(array(
    [$lat1, $lon1],
    [$lat2, $lon2],
    [$lat3, $lon3],
    [$lat4, $lon4]
));
if ($geopointA->inPolygon($polygon)) {
  echo "GeoPoint is in Polygon!";
}
```
 
Polygons use planar ray casting: they must not cross the antimeridian and must
have at least three vertices when tested. Boundary behavior is half-open, so
some edges are inside and others are outside. Use `BoundingBox::contains()` for
inclusive box boundaries. Vertices can be added incrementally with `addVertex()`.

Transform bounding box coordinates into a polygon
-------
```php
<?php

use AnthonyMartin\GeoLocation\GeoPoint;

$geopointA = new GeoPoint(40.5187154, -74.4120953);
$boundingBox = $geopointA->boundingBox(5, 'mi');
$polygon = $boundingBox->toPolygon();
```

`toPolygon()` is an alias of `getPolygon()`. Vertices now follow the perimeter
in SW, SE, NE, NW order; the previous version swapped the final two corners.
For a box that does not cross the antimeridian, polygon containment works too:
```php
if ($geopointA->inPolygon($polygon)) {
  echo "GeoPoint is in Polygon / Bounding Box!";
}
```


Exceptions and legacy API
--------
All library exceptions implement `AnthonyMartin\GeoLocation\Exceptions\ExceptionInterface`:

```php
try {
  $point = GeoPoint::fromAddress('New York', 'your-api-key');
} catch (\AnthonyMartin\GeoLocation\Exceptions\ExceptionInterface $error) {
  echo $error->getMessage();
}
```

Existing exception classes share the library's `Exceptions\Exception` base.
Invalid units throw `Exceptions\InvalidUnitException`, which also preserves
compatibility with PHP's `\InvalidArgumentException`. Use `ExceptionInterface`
to catch both families without changing existing exception inheritance.

The `GeoLocation` class remains available but is deprecated in favor of
`GeoPoint`, `BoundingBox`, and `Polygon`. Its geocoding method still returns the
complete validated Google response; `getAngular()` is zero until a bounding
radius is calculated.

Running Tests
--------
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
