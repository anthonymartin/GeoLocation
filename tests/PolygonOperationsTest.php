<?php

declare(strict_types=1);

use AnthonyMartin\GeoLocation\{Earth, GeoPoint, Polygon};
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PolygonOperationsTest extends TestCase {
    public function testSphericalAreaAndClosedPerimeter(): void {
        $cell = Polygon::fromArray([[0,0],[0,1],[1,1],[1,0]]);
        self::assertEqualsWithDelta(12364, $cell->area('km'), 1);
        self::assertEqualsWithDelta($cell->area('km'), $cell->reversed()->area('km'), 1e-8);
        self::assertEqualsWithDelta($cell->area('km') * (Earth::RADIUS_MI/Earth::RADIUS_KM)**2, $cell->area('mi'), 1e-8);
        self::assertEqualsWithDelta(444.763, $cell->perimeter('km'), .001);
        // An exact octant has solid angle pi/2, including a pole vertex.
        $octant = Polygon::fromArray([[0,0],[0,90],[90,0]]);
        self::assertEqualsWithDelta(M_PI_2 * Earth::RADIUS_KM**2, $octant->area('km'), 1e-6);
        // Four-corner approximation, not the actual surveyed statutory border.
        $colorado = Polygon::fromArray([[37,-109.05],[37,-102.05],[41,-102.05],[41,-109.05]]);
        self::assertEqualsWithDelta(269601, $colorado->area('km'), 1500);
        self::assertSame(0.0, (new Polygon())->area('km'));
        self::assertSame(0.0, (new Polygon())->perimeter('mi'));
    }

    public function testVertexMeanAndBulkIterableFactory(): void {
        $points = (static function () { foreach ([[-1,-1],[-1,1],[1,1],[1,-1]] as [$lat,$lon]) { yield new GeoPoint($lat,$lon); } })();
        $polygon = Polygon::fromGeoPoints($points);
        self::assertEqualsWithDelta(0, $polygon->centroid()->getLatitude(), 1e-12);
        self::assertEqualsWithDelta(0, $polygon->centroid()->getLongitude(), 1e-12);
        $wrapped = Polygon::fromArray([[-10,170],[-10,-170],[10,-170],[10,170]]);
        self::assertEqualsWithDelta(180, abs($wrapped->centroid()->getLongitude()), 1e-12);
        self::assertCount(4, $polygon->getVertices());
        self::assertFalse($polygon->isClockwise());
        self::assertTrue($polygon->reversed()->isClockwise());
        self::assertSame($polygon->getVertices(), $polygon->reversed()->reversed()->getVertices());
    }

    public function testUndefinedCentroidsThrow(): void {
        foreach ([new Polygon(), Polygon::fromArray([[0,0],[0,180]])] as $polygon) {
            try { $polygon->centroid(); self::fail('Expected no unique centroid'); }
            catch (InvalidArgumentException $e) { self::assertStringContainsString('centroid', $e->getMessage()); }
        }
    }

    public function testPlanarValidationFindsBowtiesDuplicatesAndTouchingEdges(): void {
        self::assertTrue(Polygon::fromArray([[0,0],[0,2],[2,2],[2,0]])->isValid());
        foreach ([[], [[0,0],[0,1]], [[0,0],[1,1],[0,1],[1,0]], [[0,0],[0,1],[0,1],[1,0]],
            [[0,0],[0,2],[2,2],[0,1],[2,0]]] as $vertices) {
            $polygon = Polygon::fromArray($vertices);
            self::assertFalse($polygon->isValid());
            self::assertNotEmpty($polygon->validationErrors());
        }
    }

    public function testSphericalContainmentCrossesAntimeridianWithoutIncludingAntipode(): void {
        $polygon = Polygon::fromArray([[-10,170],[-10,-170],[10,-170],[10,170]]);
        foreach ([$polygon,$polygon->reversed()] as $ring) {
            foreach ([[0,180],[0,-180],[0,175],[0,-175],[-10,170]] as [$lat,$lon]) {
                self::assertTrue($ring->containsSpherical(new GeoPoint($lat,$lon)));
            }
            foreach ([[0,0],[0,160],[30,180],[10,-10]] as [$lat,$lon]) {
                self::assertFalse($ring->containsSpherical(new GeoPoint($lat,$lon)));
            }
        }
        self::assertFalse($polygon->contains(new GeoPoint(0,180)));
        self::assertTrue($polygon->contains(new GeoPoint(0,0))); // Existing planar semantics.
    }

    public function testSphericalPoleCapsAndGreatCircleBoundaries(): void {
        $cap = Polygon::fromArray([[80,-120],[80,0],[80,120]]);
        foreach ([$cap,$cap->reversed()] as $ring) {
            self::assertTrue($ring->containsSpherical(new GeoPoint(90,0)));
            self::assertFalse($ring->containsSpherical(new GeoPoint(-90,0)));
            self::assertFalse($ring->containsSpherical(new GeoPoint(0,0)));
        }
        $octant = Polygon::fromArray([[0,0],[0,90],[90,0]]);
        foreach ([[30,30],[0,45],[45,0],[90,90]] as [$lat,$lon]) { self::assertTrue($octant->containsSpherical(new GeoPoint($lat,$lon))); }
        foreach ([[-30,30],[30,-30],[-30,-150],[0,-180]] as [$lat,$lon]) { self::assertFalse($octant->containsSpherical(new GeoPoint($lat,$lon))); }
        self::assertFalse((new Polygon())->containsSpherical(new GeoPoint(0,0)));
    }

    public function testRotatedRingsSmallAreasAndConcavity(): void {
        foreach ([[0,0],[45,45],[-60,179.99],[89.99,-100]] as [$lat,$lon]) {
            $center = new GeoPoint($lat,$lon);
            foreach ([.00001,1,1000] as $radius) {
                $points=[];
                for ($bearing=0;$bearing<360;$bearing+=5) { $points[]=$center->destination($radius,$bearing,'km'); }
                $ring=Polygon::fromGeoPoints($points);
                $expected=4*M_PI*Earth::RADIUS_KM**2*sin($radius/(2*Earth::RADIUS_KM))**2;
                self::assertEqualsWithDelta($expected,$ring->area('km'),$expected*.002);
                foreach ([$ring,$ring->reversed()] as $polygon) {
                    self::assertTrue($polygon->containsSpherical($center));
                    self::assertFalse($polygon->containsSpherical($center->destination($radius*1.5,30,'km')));
                    self::assertFalse($polygon->containsSpherical($center->destination(M_PI*Earth::RADIUS_KM,0,'km')));
                }
            }
        }
        $concave=Polygon::fromArray([[0,0],[0,3],[1,3],[1,1],[3,1],[3,0]]);
        self::assertTrue($concave->containsSpherical(new GeoPoint(.5,2)));
        self::assertFalse($concave->containsSpherical(new GeoPoint(2,2)));
        self::assertTrue($concave->reversed()->containsSpherical(new GeoPoint(2,.5)));
    }

    public function testAmbiguousSphericalRingsThrow(): void {
        foreach ([[[0,0],[0,180],[45,90]], [[0,0],[0,120],[0,-120]]] as $vertices) {
            try { Polygon::fromArray($vertices)->containsSpherical(new GeoPoint(30,0)); self::fail('Ambiguous ring accepted'); }
            catch (InvalidArgumentException $e) { self::assertNotEmpty($e->getMessage()); }
        }
    }

    public function testSimplificationIsImmutableAndHonorsMeterToleranceOnDenseCircles(): void {
        foreach ([new GeoPoint(0,0),new GeoPoint(70,179.99)] as $center) {
            $points = [];
            for ($bearing=0;$bearing<360;$bearing++) { $points[]=$center->destination(10,$bearing,'km'); }
            $polygon=Polygon::fromGeoPoints($points);
            $simple=$polygon->simplify(100);
            self::assertCount(360,$polygon->getVertices());
            self::assertGreaterThanOrEqual(3,count($simple->getVertices()));
            self::assertLessThan(50,count($simple->getVertices()));
            self::assertLessThan(.02,abs($simple->area('km')/$polygon->area('km')-1));
            // Every removed point lies within tolerance of a retained chord on this circle.
            $kept=$simple->getVertices();
            foreach ($points as $point) {
                $error=INF;
                foreach ($kept as $i=>$a) {
                    $b=$kept[($i+1)%count($kept)];
                    $edge=$a->distanceTo($b,'km')*1000;
                    $da=$a->distanceTo($point,'km')*1000;
                    $db=$b->distanceTo($point,'km')*1000;
                    $projection=($da*$da+$edge*$edge-$db*$db)/(2*$edge);
                    if ($projection>=0 && $projection<=$edge) { $error=min($error,sqrt(max(0,$da*$da-$projection*$projection))); }
                    $error=min($error,$da,$db);
                }
                self::assertLessThanOrEqual(100.01,$error);
            }
            self::assertSame($polygon,$polygon->simplify(0));
            self::assertSame($polygon,$polygon->simplify(1e9));
        }
        foreach ([-1,INF,NAN] as $tolerance) {
            try { (new Polygon())->simplify($tolerance); self::fail('Bad tolerance'); }
            catch (InvalidArgumentException $e) { self::assertStringContainsString('tolerance',$e->getMessage()); }
        }
    }
}
