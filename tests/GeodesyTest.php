<?php

declare(strict_types=1);

use AnthonyMartin\GeoLocation\{Earth, GeoPoint};
use AnthonyMartin\GeoLocation\Geodesy\{Ellipsoid, Geodesic, GeodesicResult, Vincenty};
use AnthonyMartin\GeoLocation\Exceptions\{GeodesicConvergenceException, InvalidArgumentException};
use PHPUnit\Framework\TestCase;

final class GeodesyTest extends TestCase {
    public function testKarneyPublishedReferenceSample(): void {
        $solver = new Vincenty(); $solved = 0; $unsupported = 0;
        $rows = file(__DIR__.'/fixtures/geodesic-reference.dat', FILE_IGNORE_NEW_LINES);
        self::assertCount(1000, $rows);
        foreach ($rows as $index => $line) {
            [$lat1,$lon1,$azi1,$lat2,$lon2,$azi2,$meters] = array_map('floatval', explode(' ', $line));
            $a = new GeoPoint($lat1,$lon1); $b = new GeoPoint($lat2,$lon2);
            // Direct remains supported even when inverse is ill-conditioned.
            $end = $solver->direct($a,$meters,$azi1);
            self::assertLessThan(.001, $end->distanceTo($b,'km')*1000, "direct row $index");
            try { $result = $solver->inverse($a,$b); }
            catch (GeodesicConvergenceException) {
                self::assertTrue($a->isAntipodeOf($b, deg2rad(2)*Earth::RADIUS_KM*1000), "unexpected exclusion $index");
                $unsupported++; continue;
            }
            self::assertEqualsWithDelta($meters,$result->distanceMeters,.001,"distance row $index");
            self::assertEqualsWithDelta($azi1,$result->initialBearing,1e-8,"initial bearing row $index");
            self::assertEqualsWithDelta($azi2,$result->finalBearing,1e-8,"final bearing row $index");
            $solved++;
        }
        self::assertSame(599,$solved);
        self::assertSame(401,$unsupported);
    }

    public function testVincentyPaperExamplesOnTheirOriginalEllipsoids(): void {
        // Vincenty (1975), Table II, examples (a) and (e):
        // https://geodesy.noaa.gov/PUBS_LIB/inverse.pdf
        $solver = new Vincenty(new Ellipsoid(6377397.155, 1/299.1528128));
        $a = new GeoPoint(55.75,0); $b = new GeoPoint(-33-26/60,108+13/60);
        $inverse = $solver->inverse($a,$b);
        self::assertEqualsWithDelta(14110526.170,$inverse->distanceMeters,.001);
        self::assertEqualsWithDelta(96+36/60+8.79960/3600,$inverse->initialBearing,1e-8);
        self::assertEqualsWithDelta(137+52/60+22.01454/3600,$inverse->finalBearing,1e-8);
        $solver = new Vincenty(new Ellipsoid(6378388,1/297));
        $end = $solver->direct(new GeoPoint(1,0),19780006.558,4+59/60+59.99995/3600);
        $expected = new GeoPoint(1+1/60+15.18952/3600,179+46/60+17.84244/3600);
        self::assertLessThan(.001,$end->distanceTo($expected,'km')*1000);
    }

    public function testUnitsAndSphericalDifferenceAreExplicit(): void {
        $a=new GeoPoint(0,0);$b=new GeoPoint(1,0);$solver=new Vincenty();
        self::assertInstanceOf(Geodesic::class,$solver);
        $result=$solver->inverse($a,$b);
        self::assertGreaterThan(110.57,$result->distance('km'));
        self::assertLessThan(110.58,$result->distance('km'));
        self::assertGreaterThan(.5,$a->distanceTo($b,'km')-$result->distance('km'));
        self::assertSame($result->distanceMeters/1609.344,$result->distance('MI'));
        self::assertSame(0.0,$solver->inverse($a,$a)->distanceMeters);
        self::assertSame(0.0,$solver->direct($a,0,90)->getLongitude());
        self::assertEqualsWithDelta(6356752.314245179,(new Ellipsoid())->b,1e-6);
        self::assertEqualsWithDelta(1/298.257222101,(new Ellipsoid(...Ellipsoid::GRS80))->f,1e-16);
        self::assertSame(6378137.0,(new Ellipsoid(...Ellipsoid::WGS84))->a);
    }

    public function testAntipodalInverseThrowsAndInvalidValuesAreRejected(): void {
        $solver=new Vincenty();$a=new GeoPoint(0,0);
        foreach ([new GeoPoint(0,180),new GeoPoint(.5,179.7)] as $b) {
            try {$solver->inverse($a,$b);self::fail('Unreliable inverse accepted');}
            catch (GeodesicConvergenceException $e) {self::assertStringContainsString('antipode',$e->getMessage());}
        }
        foreach ([fn()=>new Ellipsoid(0),fn()=>new Ellipsoid(INF),fn()=>new Ellipsoid(1,1),
            fn()=>new GeodesicResult(-1,0,0),fn()=>$solver->direct($a,-1,0),fn()=>$solver->direct($a,1,NAN)] as $call) {
            try {$call();self::fail('Invalid geodesy input accepted');}
            catch (InvalidArgumentException) {self::assertTrue(true);}
        }
    }
}
