<?php

declare(strict_types=1);

use AnthonyMartin\GeoLocation\{BoundingBox, GeoPoint};
use AnthonyMartin\GeoLocation\Index\Geohash;
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GeohashTest extends TestCase {
    public function testCanonicalVectorsAndReferenceNeighborTables(): void {
        // Gustavo Niemeyer's encoding, checked against Chris Veness's reference:
        // https://github.com/chrisveness/latlon-geohash
        self::assertSame('ezs42',Geohash::encode(new GeoPoint(42.6,-5.6),5));
        self::assertSame('u4pruydqqvj',Geohash::encode(new GeoPoint(57.64911,10.40744),11));
        $center=Geohash::decode('ezs42');self::assertEqualsWithDelta(42.605,$center->getLatitude(),.001);self::assertEqualsWithDelta(-5.603,$center->getLongitude(),.001);
        $box=Geohash::decodeBox('ezs42');self::assertSame(42.5830078125,$box->getMinLatitude());self::assertSame(-5.5810546875,$box->getMaxLongitude());
        self::assertSame(['N'=>'ezs48','NE'=>'ezs49','E'=>'ezs43','SE'=>'ezs41','S'=>'ezs40','SW'=>'ezefp','W'=>'ezefr','NW'=>'ezefx'],Geohash::neighbors('ezs42'));
        self::assertSame(['N'=>'pbpb','NE'=>'0000','E'=>'bpbp','SE'=>'bpbn','S'=>'zzzy','SW'=>'zzzw','W'=>'zzzx','NW'=>'pbp8'],Geohash::neighbors('zzzz'));
        self::assertSame(['N'=>'0001','NE'=>'0003','E'=>'0002','SE'=>'bpbr','S'=>'bpbp','SW'=>'zzzz','W'=>'pbpb','NW'=>'pbpc'],Geohash::neighbors('0000'));
    }

    public function testRoundTripAtEveryPrecisionIncludingEndpoints(): void {
        mt_srand(76);
        $points=[new GeoPoint(90,180),new GeoPoint(-90,-180),new GeoPoint(0,0)];
        for($i=0;$i<100;$i++) {$points[]=new GeoPoint(mt_rand(-900000,900000)/10000,mt_rand(-1800000,1800000)/10000);}
        foreach(range(1,12) as $precision) {foreach($points as $point) {
            $hash=Geohash::encode($point,$precision);$box=Geohash::decodeBox($hash);$center=Geohash::decode($hash);
            self::assertSame($precision,strlen($hash));self::assertTrue($box->contains($point));
            self::assertSame($hash,Geohash::encode($center,$precision));
            self::assertLessThanOrEqual(($box->getMaxLatitude()-$box->getMinLatitude())/2+1e-12,abs($point->getLatitude()-$center->getLatitude()));
            self::assertLessThanOrEqual(($box->getMaxLongitude()-$box->getMinLongitude())/2+1e-12,abs($point->getLongitude()-$center->getLongitude()));
        }}
    }

    public function testExactBoxCoversAgainstExhaustiveGrid(): void {
        $alphabet='0123456789bcdefghjkmnpqrstuvwxyz';
        $boxes=[new BoundingBox(-1,-1,1,1),new BoundingBox(-10,170,10,-170),new BoundingBox(0,180,0,180),new BoundingBox(-90,-180,90,180),new BoundingBox(80,-15,90,15)];
        foreach($boxes as $region) {
            $expected=[];
            foreach(str_split($alphabet) as $first) {foreach(str_split($alphabet) as $second) {
                $hash=$first.$second;$cell=Geohash::decodeBox($hash);
                $lat=$cell->getMaxLatitude()>=$region->getMinLatitude() && $cell->getMinLatitude()<=$region->getMaxLatitude();
                $lon=$region->getMinLongitude()<=$region->getMaxLongitude()
                    ? $cell->getMaxLongitude()>=$region->getMinLongitude() && $cell->getMinLongitude()<=$region->getMaxLongitude()
                    : $cell->getMaxLongitude()>=$region->getMinLongitude() || $cell->getMinLongitude()<=$region->getMaxLongitude();
                if(($region->getMinLongitude()===180.0||$region->getMaxLongitude()===180.0)&&$cell->getMinLongitude()===-180.0) {$lon=true;}
                if(($region->getMinLongitude()===-180.0||$region->getMaxLongitude()===-180.0)&&$cell->getMaxLongitude()===180.0) {$lon=true;}
                if($lat&&$lon) {$expected[]=$hash;}
            }}
            sort($expected,SORT_STRING);self::assertSame($expected,Geohash::cover($region,2));
        }
        $circle=(new GeoPoint(0,179.9))->circle(20,'km');
        self::assertSame(Geohash::cover($circle->boundingBox(),4),Geohash::cover($circle,4));
        foreach([new GeoPoint(0,179.9),new GeoPoint(0,-179.95)] as $point) {self::assertContains(Geohash::encode($point,4),Geohash::cover($circle,4));}
    }

    public function testPrecisionHeuristicAndSizeGuard(): void {
        self::assertSame(12,Geohash::precisionForRadius(0,'km'));
        self::assertGreaterThan(Geohash::precisionForRadius(100,'km'),Geohash::precisionForRadius(1,'km'));
        self::assertSame(1,Geohash::precisionForRadius(100000,'km'));
        foreach([fn()=>Geohash::encode(new GeoPoint(0,0),0),fn()=>Geohash::decode(''),fn()=>Geohash::decode('a'),fn()=>Geohash::decode(str_repeat('z',13)),
            fn()=>Geohash::precisionForRadius(-1,'km'),fn()=>Geohash::precisionForRadius(INF,'km'),fn()=>Geohash::cover(new BoundingBox(-90,-180,90,180),12)] as $call) {
            try {$call();self::fail('Invalid or oversized index operation accepted');}catch(InvalidArgumentException){self::assertTrue(true);}
        }
    }
}
