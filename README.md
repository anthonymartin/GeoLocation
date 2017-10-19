# GeoLocation
[![Software License][ico-license]](LICENSE.md)

Retrieve bounding coordinates and distances with GeoLocation.
You can also calculate and measure the distance between geopoints with this php class.

>**Note:** This is a PHP port of Java code that was originally published by
[Jan Philip Matuschek](http://JanMatuschek.de/LatitudeLongitudeBoundingCoordinates). The package has few modifications made and an additional helper method to retrieve latitude and longitude from an address has been provided using Google's Geocoding API. <br />

## Installation
```bash
$ composer require anthonymartin/geo-location
```

## Testing
```bash 
$ ./bin/vendor/phpunit
```
## Usage

The following use statement is required for the examples below:
```php
.
.
use  AnthonyMartin\GeoLocation;

```

### Get distance between two points:
```php

$origin = GeoLocation::fromDegrees($latitude, $longitude);
$destination = GeoLocation::fromDegrees($latitude, $longitude);

// Get distance in Miles
$origin->distanceTo($destination, 'miles')

// Get distance in Kilometers
$origin->distanceTo($destination, 'kilometers')

```
### Get bounding coordinates

```php
		
$location = GeoLocation::fromDegrees(40.5187154, -74.4120953);
$coordinates = $location->boundingCoordinates(3, 'miles');

$coordinates[0]->getLatitudeInDegrees();
// 40.47529593323

$coordinates[0]->getLongitudeInDegrees();
// -74.469211617725

$coordinates[1]->getLatitudeInDegrees();
// 40.56213486677

$coordinates[1]->getLongitudeInDegrees();
// -74.354978982275

```

### Get latitude and longitude from address or location

```php

$location = 'New York City';

$response = GeoLocation::getGeocodeFromGoogle($location);

$latitude = $response->results[0]->geometry->location->lat;
// 40.7143528

$longitude = $response->results[0]->geometry->location->lng;
// -74.0059731

```

## Credits

- [All Contributers](https://github.com/anthonymartin/GeoLocation.php/graphs/contributors)


## Donate
- ***Bitcoin:*** 1PPLC86abWCb3Ahez14vcJPUan6Zes9D5t
- ***Ether:*** 0x92f59580479eaf61d4d81ee8441ff23fb1ec57dc
- ***Litecoin:*** LPdsE3eHKnoxGa8yUkLoCwr7NXdh4WorRW

## License

Attribution 3.0 Unported (CC BY 3.0) . Please see [License File](http://creativecommons.org/licenses/by/3.0/) for more information.
