<?php

declare(strict_types=1);

use AnthonyMartin\GeoLocation\{BoundingBox, Circle, GeoPoint, Polygon};
use AnthonyMartin\GeoLocation\Format\{GeoJson, Wkt};
use AnthonyMartin\GeoLocation\Exceptions\{ExceptionInterface, UnsupportedGeometryException};
use PHPUnit\Framework\TestCase;

final class FormatTest extends TestCase {
    public function testRfcPointAndPolygonCoordinateOrder(): void {
        // RFC 7946 Appendix A.1/A.3 coordinate values.
        $point=GeoJson::decode('{"type":"Point","coordinates":[100.0,0.0]}');
        self::assertSame(0.0,$point->getLatitude());self::assertSame(100.0,$point->getLongitude());
        self::assertSame([100.0,0.0],GeoJson::encode($point)['coordinates']);
        self::assertSame([100.0,0.0,100.0,0.0],GeoJson::encode($point)['bbox']);
        $polygon=GeoJson::decode(['type'=>'Polygon','coordinates'=>[[[100,0],[101,0],[101,1],[100,1],[100,0]]]]);
        self::assertCount(4,$polygon->getVertices());
        self::assertTrue($polygon->contains(new GeoPoint(.5,100.5)));
        $encoded=GeoJson::encode($polygon);self::assertCount(5,$encoded['coordinates'][0]);
        self::assertSame($encoded['coordinates'][0][0],$encoded['coordinates'][0][4]);
        self::assertSame('POINT (100.0 0.0)',Wkt::encode($point));
        self::assertSame(100.0,Wkt::decode('point (1e2 +.0)')->getLongitude());
        self::assertSame(0.0,Wkt::decode('POINT (100 0)')->getLatitude());
    }

    public function testRandomRoundTripsThroughBothCodecs(): void {
        mt_srand(74);
        for($i=0;$i<100;$i++) {
            $lat=mt_rand(-800000,800000)/10000;$lon=mt_rand(-1700000,1700000)/10000;
            $point=new GeoPoint($lat,$lon);
            foreach([GeoJson::decode(GeoJson::encodeToString($point)),Wkt::decode(Wkt::encode($point))] as $decoded) {
                self::assertSame($lat,$decoded->getLatitude());self::assertSame($lon,$decoded->getLongitude());
            }
            $box=new BoundingBox($lat,$lon,$lat+1,$lon+1);
            foreach([GeoJson::decode(GeoJson::encodeToString($box)),Wkt::decode(Wkt::encode($box))] as $decoded) {
                self::assertCount(4,$decoded->getVertices());
                self::assertTrue($decoded->contains(new GeoPoint($lat+.5,$lon+.5)));
                self::assertFalse($decoded->contains(new GeoPoint($lat+2,$lon+.5)));
            }
        }
    }

    public function testWrappedBoxIsCutForMapsAndRetainsRfcBbox(): void {
        $box=new BoundingBox(-20,177,-16,-178);
        $json=GeoJson::encode($box);
        self::assertSame([177.0,-20.0,-178.0,-16.0],$json['bbox']);
        self::assertSame('MultiPolygon',$json['type']);self::assertCount(2,$json['coordinates']);
        foreach($json['coordinates'] as $polygon) {
            self::assertCount(5,$polygon[0]);
            self::assertSame($polygon[0][0],$polygon[0][4]);
            foreach($polygon[0] as [$lon,$lat]) {self::assertGreaterThanOrEqual(177,abs($lon));}
        }
        $this->expectException(UnsupportedGeometryException::class);
        GeoJson::decode($json); // Decoding multipart geometries deliberately remains unsupported.
    }

    public function testCircleApproximationAndPolarCap(): void {
        $circle=new Circle(new GeoPoint(40,-74),5,'km');
        $encoded=GeoJson::encode($circle,16);
        self::assertCount(17,$encoded['coordinates'][0]);
        foreach(array_slice($encoded['coordinates'][0],0,-1) as [$lon,$lat]) {
            self::assertEqualsWithDelta(5,$circle->getCenter()->distanceTo(new GeoPoint($lat,$lon),'km'),1e-8);
        }
        $wrapped=GeoJson::encode(new Circle(new GeoPoint(0,179.9),50,'km'),32);
        self::assertSame('MultiPolygon',$wrapped['type']);self::assertCount(2,$wrapped['coordinates']);
        self::assertGreaterThan($wrapped['bbox'][2],$wrapped['bbox'][0]);
        $polar=GeoJson::encode(new Circle(new GeoPoint(89.9,30),50,'km'),32);
        self::assertSame(90.0,$polar['bbox'][3]);
        $polygons=$polar['type']==='Polygon'?[$polar['coordinates']]:$polar['coordinates'];
        $hasPole=false;
        foreach($polygons as $rings) {foreach($rings[0] as [$lon,$lat]) {if($lat===90.0)$hasPole=true;self::assertGreaterThanOrEqual(-180,$lon);self::assertLessThanOrEqual(180,$lon);}}
        self::assertTrue($hasPole);
        self::assertSame('Point',GeoJson::encode(new Circle(new GeoPoint(1,2),0,'km'))['type']);
    }

    public function testMalformedInputAlwaysRaisesLibraryExceptions(): void {
        $badJson=[null,1,'{','null',[],['type'=>'Point','coordinates'=>[1]],['type'=>'Point','coordinates'=>[1,2,3]],
            ['type'=>'Point','coordinates'=>['1',2]],['type'=>'Point','coordinates'=>[NAN,0]],
            ['type'=>'Polygon','coordinates'=>[[[0,0],[1,0],[1,1],[2,2]]]],
            ['type'=>'Polygon','coordinates'=>[[[0,0],[1,1]]]],['type'=>'Polygon','coordinates'=>[null]],
            ['type'=>'Polygon','coordinates'=>[[[0,0],[1,0],[1,1],[0,0]],[[.1,.1],[.2,.1],[.2,.2],[.1,.1]]]],
            ['type'=>'Feature','geometry'=>['type'=>'Point','coordinates'=>[0,0]]],['type'=>'MultiPolygon','coordinates'=>[]]];
        foreach($badJson as $bad) {
            try {GeoJson::decode($bad);self::fail('Invalid GeoJSON accepted');}catch(ExceptionInterface){self::assertTrue(true);}
        }
        foreach([null,'POINT (1 2 3)','POINT (NaN 2)','POLYGON ((0 0, 1 0, 1 1, 2 2))','POLYGON ((0 0,1 0,1 1,0 0),(0 0,1 0,1 1,0 0))','POINT EMPTY','SRID=4326;POINT(1 2)','POINT (1e999 0)'] as $bad) {
            try {Wkt::decode($bad);self::fail('Invalid WKT accepted');}catch(ExceptionInterface){self::assertTrue(true);}
        }
        foreach([fn()=>GeoJson::encode(new Polygon()),fn()=>GeoJson::encode(new Circle(new GeoPoint(0,0),1,'km'),2),fn()=>GeoJson::encode(new Circle(new GeoPoint(0,0),20000,'km'))] as $call) {
            try {$call();self::fail('Unsupported geometry accepted');}catch(ExceptionInterface){self::assertTrue(true);}
        }
    }
}
