<?php

declare(strict_types=1);

use AnthonyMartin\GeoLocation\{Earth, GeoPoint};
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NavigationTest extends TestCase {
    public function testVenessReferenceExamples(): void {
        // https://www.movable-type.co.uk/scripts/latlong.html and geodesy/test/latlon-spherical-tests.js
        // Full-precision values independently evaluated with Veness's JS implementation;
        // destination uses this library's documented radius 6371010 m, not 6371000 m.
        $a = new GeoPoint(52.205, 0.119); $b = new GeoPoint(48.857, 2.351);
        self::assertEqualsWithDelta(156.16658258153174, $a->bearingTo($b), 1e-6);
        self::assertEqualsWithDelta(157.89044019049243, $a->finalBearingTo($b), 1e-6);
        self::assertEqualsWithDelta(50.53632687827434, $a->midpointTo($b)->getLatitude(), 1e-6);
        self::assertEqualsWithDelta(1.2746141006782286, $a->midpointTo($b)->getLongitude(), 1e-6);
        self::assertEqualsWithDelta(51.37208385546636, $a->interpolate($b, .25)->getLatitude(), 1e-6);
        self::assertEqualsWithDelta(.7073371009198176, $a->interpolate($b, .25)->getLongitude(), 1e-6);
        $end = (new GeoPoint(51.47788, -.00147))->destination(7.794, 300.7, 'km');
        self::assertEqualsWithDelta(51.513625635584226, $end->getLatitude(), 1e-6);
        self::assertEqualsWithDelta(-.09831539939918839, $end->getLongitude(), 1e-6);
    }

    public function testCardinalsPolesWrappingAndZeroDistance(): void {
        $origin = new GeoPoint(0, 0); $quarter = M_PI_2 * Earth::RADIUS_KM;
        foreach ([[0,90,0], [90,0,90], [180,-90,0], [270,0,-90], [-90,0,-90], [360,90,0]] as [$bearing,$lat,$lon]) {
            $end = $origin->destination($quarter, $bearing, 'km');
            self::assertLessThan(0.000001, $end->distanceTo(new GeoPoint($lat,$lon), 'km'));
        }
        $pole = new GeoPoint(90, 30);
        self::assertSame(180.0, $pole->bearingTo(new GeoPoint(0, -40)));
        self::assertSame(0.0, (new GeoPoint(-90, 10))->bearingTo($origin));
        self::assertEqualsWithDelta(30, $pole->destination(100, 180, 'km')->getLongitude(), 1e-6);
        $wrapped = (new GeoPoint(0, 179))->destination(300, 90, 'km');
        self::assertLessThan(-178, $wrapped->getLongitude());
        self::assertSame(179.0, (new GeoPoint(0,179))->destination(0, 123, 'km')->getLongitude());
        self::assertEqualsWithDelta(180, abs((new GeoPoint(0,170))->midpointTo(new GeoPoint(0,-170))->getLongitude()), 1e-6);
        self::assertEqualsWithDelta(45, $pole->midpointTo($origin)->getLatitude(), 1e-6);
    }

    public function testSeededRandomRoundTripsWithinOneMillimeter(): void {
        mt_srand(71);
        for ($i=0; $i<500; $i++) {
            $a=new GeoPoint(mt_rand(-899999,899999)/10000,mt_rand(-1800000,1800000)/10000);
            $b=new GeoPoint(mt_rand(-899999,899999)/10000,mt_rand(-1800000,1800000)/10000);
            $end=$a->destination($a->distanceTo($b,'km'),$a->bearingTo($b),'km');
            self::assertLessThan(.000001,$end->distanceTo($b,'km'));
            self::assertEqualsWithDelta($a->distanceTo($b,'km')/2,$a->distanceTo($a->midpointTo($b),'km'),1e-6);
        }
    }

    public function testUndefinedRoutesAndInvalidParametersThrow(): void {
        $p=new GeoPoint(0,0); $opposite=new GeoPoint(0,180);
        self::assertTrue($p->isAntipodeOf($opposite));
        self::assertFalse($p->isAntipodeOf(new GeoPoint(0,179)));
        foreach ([fn()=>$p->bearingTo($p),fn()=>$p->bearingTo($opposite),fn()=>$p->midpointTo($opposite),
            fn()=>$p->interpolate($opposite,0),fn()=>$p->interpolate($p,NAN),fn()=>$p->interpolate($p,1.1),
            fn()=>$p->destination(-1,0,'km'),fn()=>$p->destination(1,INF,'km'),fn()=>$p->isAntipodeOf($p,-1)] as $call) {
            try { $call(); self::fail('Invalid navigation accepted'); }
            catch (InvalidArgumentException) { self::assertTrue(true); }
        }
        self::assertSame(0.0,$p->interpolate($p,.5)->getLatitude());
    }
}
