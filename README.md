Follow the author: @pressplayplease

GeoLocation
===========

Retrieve bounding coordinates and distances with GeoLocation.
You can also calculate and measure the distance between geopoints with this php class.

This is a PHP port of Java code that was originally published at
<a href="http://JanMatuschek.de/LatitudeLongitudeBoundingCoordinates">
http://JanMatuschek.de/LatitudeLongitudeBoundingCoordinates</a>. A few modifications were made and an additional helper method to retrieve latitude and longitude from an address has been provided using Google's Geocoding API. <br />

License:
http://creativecommons.org/licenses/by/3.0/

Examples
========

Get distance between two points:
--------------------------------------------------------
<pre>
	<code>
		use AnthonyMartin\GeoLocation\GeoLocation as GeoLocation;
		
		// Set locations
		$edison_nj = GeoLocation::fromDegrees(40.5187154, -74.4120953);
		$brooklyn_ny = GeoLocation::fromDegrees(40.65, -73.95);

		echo "Distance from Edison, NJ to Brookyln, NY: " . 
			$edison_nj->distanceTo($brooklyn_ny, 'miles') . " miles \n";

		# Distance from Edison, NJ to Brookyln, NY: 25.888611494606 miles 


		echo "Distance from Edison, NJ to Brooklyn, NY: " . 
			$edison_nj->distanceTo($brooklyn_ny, 'kilometers') . " kilometers \n";

		# Distance from Edison, NJ to Brooklyn, NY: 41.663681581973 kilometers 

	</code>
</pre>


Get bounding coordinates
--------------------------------------------------------
<pre>
	<code>
		use AnthonyMartin\GeoLocation\GeoLocation as GeoLocation;
		
		$edison = GeoLocation::fromDegrees(40.5187154, -74.4120953);
		$coordinates = $edison->boundingCoordinates(3, 'miles');

		echo "min latitude: " . $coordinates[0]->getLatitudeInDegrees() . " \n";
		echo "min longitude: " . $coordinates[0]->getLongitudeInDegrees() . " \n";

		echo "max latitude: " . $coordinates[1]->getLatitudeInDegrees() . " \n";
		echo "max longitude: " . $coordinates[1]->getLongitudeInDegrees() . " \n";

		/**
		*	Returns:
		*	min latitude: 40.47529593323 
		*	min longitude: -74.469211617725 
		*	max latitude: 40.56213486677 
		*	max longitude: -74.354978982275 
		**/

	</code>
</pre>

Get latitude and longitude from address or location
--------------------------------------------------------
<pre>
	<code>
	use AnthonyMartin\GeoLocation\GeoLocation as GeoLocation;
	
	$location = 'New York City';
	$response = GeoLocation::getGeocodeFromGoogle($location);
	$latitude = $response->results[0]->geometry->location->lat;
	$longitude = $response->results[0]->geometry->location->lng;
	echo $latitude . ', ' . $longitude;
	# 40.7143528, -74.0059731
	</code>
</pre>
