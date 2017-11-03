<?php
declare(strict_types = 1);

use AnthonyMartin\GeoLocation\GeoLocation;
use PHPUnit\Framework\TestCase;

final class GeoLocationTest extends TestCase
{
	public $edison_nj;
	public $brooklyn_ny;

	public function setup(): void
	{
		// Set locations
		$this->edison_nj = GeoLocation::fromDegrees(40.5187154, -74.4120953);
		$this->brooklyn_ny = GeoLocation::fromDegrees(40.65, -73.95);
	}

    /** @test*/
    public function can_get_distance_between_two_points(): void
    {
		// Distance from Edison, NJ to Brookyln, NY: 25.888611494606 miles 
		$dist_in_miles = $this->edison_nj->distanceTo($this->brooklyn_ny, 'miles');
		$this->assertEquals(25.888611494606, $dist_in_miles);

		// Distance from Edison, NJ to Brooklyn, NY: 41.663681581973 kilometers 
		$dist_in_km = $this->edison_nj->distanceTo($this->brooklyn_ny, 'kilometers');
		$this->assertEquals(41.66368158208156, $dist_in_km);
    }
}