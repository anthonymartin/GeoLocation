GeoLocation for PHP
===========

GeoLocation for PHP offers convenient and easy to use methods for geocoding, geolocation and geometry functions in PHP.

Features include:

1. Retrieve bounding box coordinates. Just define a coordinate and the size of your bounding box.
2. calculate distances between geopoints/coordinates
3. Solve point in polygon problems (identify whether a given point is within the bounds of a polygon)



Examples
========

Get distance between two points:
--------------------------------------------------------
```php
<?php

use AnthonyMartin\GeoLocation\GeoPoint;

$geopointA = new GeoPoint(40.5187154, -74.4120953);
$geopointB = new GeoPoint(40.65, -73.95);
$geopointB = $geopointA->distanceTo($geopointB, 'miles');
```


Get latitude and longitude from address or location
--------------------------------------------------------
In order to use this method, you'll need to register at [Google Cloud Console](https://console.cloud.google.com) and enable the [Geocoding API](https://console.cloud.google.com/google/maps-apis/apis/geocoding-backend.googleapis.come)

```php
<?php
use AnthonyMartin\GeoLocation\GeoPoint;

$geopoint = GeoPoint::fromAddress('New York, NY 10001', 'google-api-key-goes-here');
$latitude = $geopoint->getLatitude();
$longitude = $geopoint->getLongitude();

````

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
 
Transform bounding box coordinates into a polygon
-------
```php
<?php

use AnthonyMartin\GeoLocation\GeoPoint;

$geopointA = new GeoPoint(40.5187154, -74.4120953);
$boundingBox = $geopointA->boundingBox(5, 'mi');
$polygon = $boundingBox->toPolygon();
```

and now you can check if the GeoPoint is the polygon / bounding box:
```php
if ($geopointA->inPolygon($polygon)) {
  echo "GeoPoint is in Polygon / Bounding Box!";
}
```


Running Tests
--------
Run the following from the project directory:
```bash
./vendor/bin/phpunit tests
```




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
