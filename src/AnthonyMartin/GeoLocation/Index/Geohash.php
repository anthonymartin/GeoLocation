<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Index;

use AnthonyMartin\GeoLocation\{BoundingBox, Earth, GeoPoint};
use AnthonyMartin\GeoLocation\Interfaces\{BoundingBoxInterface, GeoPointInterface, Region};
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;

/** Base32 geohashes with conventional rectangular-grid neighbors. */
final class Geohash {
    private const string ALPHABET='0123456789bcdefghjkmnpqrstuvwxyz';

    /** Encode a validated point at 1–12 characters; exact upper endpoints use the last cell. */
    public static function encode(GeoPointInterface $p,int $precision=12): string {
        [$columns,$rows]=self::dimensions($precision);
        $point=new GeoPoint($p->getLatitude(),$p->getLongitude());
        $x=min($columns-1,(int)floor(($point->getLongitude()+180)/360*$columns));
        $y=min($rows-1,(int)floor(($point->getLatitude()+90)/180*$rows));
        return self::fromIndices($x,$y,$precision);
    }

    /** Return the full-precision cell center; decoding is necessarily approximate. */
    public static function decode(string $hash): GeoPointInterface {
        $box=self::decodeBox($hash);
        return new GeoPoint(($box->getMinLatitude()+$box->getMaxLatitude())/2,($box->getMinLongitude()+$box->getMaxLongitude())/2);
    }

    /** Return the cell's inclusive latitude/longitude envelope. */
    public static function decodeBox(string $hash): BoundingBoxInterface {
        [$x,$y,$precision]=self::indices($hash);[$columns,$rows]=self::dimensions($precision);
        return new BoundingBox($y*180/$rows-90,$x*360/$columns-180,($y+1)*180/$rows-90,($x+1)*360/$columns-180);
    }

    /** @return array{N:string,NE:string,E:string,SE:string,S:string,SW:string,W:string,NW:string} Grid neighbors wrap at both coordinate endpoints. */
    public static function neighbors(string $hash): array {
        [$x,$y,$precision]=self::indices($hash);[$columns,$rows]=self::dimensions($precision);
        $at=static fn(int $dx,int $dy): string=>self::fromIndices(self::wrap($x+$dx,$columns),self::wrap($y+$dy,$rows),$precision);
        return ['N'=>$at(0,1),'NE'=>$at(1,1),'E'=>$at(1,0),'SE'=>$at(1,-1),'S'=>$at(0,-1),'SW'=>$at(-1,-1),'W'=>$at(-1,0),'NW'=>$at(-1,1)];
    }

    /** Finest precision whose equatorial cell spans at least the diameter; use cover() for actual coverage. */
    public static function precisionForRadius(float $distance,string $unit): int {
        $radius=Earth::getRadius($unit);
        if(!is_finite($distance)||$distance<0) {throw new InvalidArgumentException('Geohash radius must be finite and nonnegative.');}
        if($distance===0.0) {return 12;}
        for($precision=12;$precision>=1;$precision--) {
            [$columns,$rows]=self::dimensions($precision);
            $span=deg2rad(min(360/$columns,180/$rows))*$radius;
            if($span/2 >= $distance) {return $precision;}
        }
        return 1;
    }

    /**
     * Exact intersecting cells for a box; circle/polygon/custom regions use their envelope.
     * Includes cells touching closed boundaries. Throws before allocating an oversized result.
     * @return list<string>
     */
    public static function cover(Region $region,int $precision,int $maxCells=100000): array {
        [$columns,$rows]=self::dimensions($precision);
        if($maxCells<1) {throw new InvalidArgumentException('Geohash maxCells must be positive.');}
        $box=$region->boundingBox();
        [$firstY,$lastY]=self::range($box->getMinLatitude(),$box->getMaxLatitude(),90,$rows);
        $west=$box->getMinLongitude();$east=$box->getMaxLongitude();
        $intervals=$west<=$east?[[$west,$east]]:[[$west,180.0],[-180.0,$east]];
        $ranges=[];
        foreach($intervals as [$min,$max]) {$ranges[]=self::range($min,$max,180,$columns);}
        if($west===-180.0 || $east===-180.0) {$ranges[]=[$columns-1,$columns-1];}
        if($west===180.0 || $east===180.0) {$ranges[]=[0,0];}
        usort($ranges,static fn(array $a,array $b): int=>$a[0]<=>$b[0]);
        /** @var list<array{int,int}> $merged */
        $merged=[];
        foreach($ranges as [$start,$end]) {
            $last=count($merged)-1;
            if($last>=0 && $start<=$merged[$last][1]+1) {$merged[$last][1]=max($merged[$last][1],$end);}
            else {$merged[]=[$start,$end];}
        }
        $count=0.0;
        foreach($merged as [$start,$end]) {$count+=(float)($end-$start+1)*($lastY-$firstY+1);}
        if($count>$maxCells) {throw new InvalidArgumentException('Geohash cover exceeds maxCells; lower precision or explicitly raise the limit.');}
        $hashes=[];
        foreach($merged as [$start,$end]) {for($x=$start;$x<=$end;$x++) {for($y=$firstY;$y<=$lastY;$y++) {$hashes[]=self::fromIndices($x,$y,$precision);}}}
        sort($hashes,SORT_STRING);
        return $hashes;
    }

    private static function wrap(int $value,int $size): int {
        return $value<0?$value+$size:($value>=$size?$value-$size:$value);
    }

    /** @return array{int,int} */
    private static function dimensions(int $precision): array {
        if($precision<1||$precision>12) {throw new InvalidArgumentException('Geohash precision must be between 1 and 12.');}
        $bits=5*$precision;
        return [1<<intdiv($bits+1,2),1<<intdiv($bits,2)];
    }

    /** @return array{int,int,int} */
    private static function indices(string $hash): array {
        $hash=strtolower($hash);$precision=strlen($hash);self::dimensions($precision);
        $x=0;$y=0;$longitude=true;
        for($i=0;$i<$precision;$i++) {
            $value=strpos(self::ALPHABET,$hash[$i]);
            if($value===false) {throw new InvalidArgumentException('Invalid geohash character.');}
            for($bit=4;$bit>=0;$bit--) {
                $digit=($value>>$bit)&1;
                if($longitude) {$x=($x<<1)|$digit;}else{$y=($y<<1)|$digit;}
                $longitude=!$longitude;
            }
        }
        return [$x,$y,$precision];
    }

    private static function fromIndices(int $x,int $y,int $precision): string {
        $bits=$precision*5;$lonBits=intdiv($bits+1,2);$latBits=intdiv($bits,2);$hash='';$value=0;
        for($i=0;$i<$bits;$i++) {
            $shift=($i%2===0?$lonBits:$latBits)-1-intdiv($i,2);
            $value=($value<<1)|((($i%2===0?$x:$y)>>$shift)&1);
            if($i%5===4) {$hash.=self::ALPHABET[$value];$value=0;}
        }
        return $hash;
    }

    /** @return array{int,int} */
    private static function range(float $minimum,float $maximum,float $halfExtent,int $cells): array {
        $start=(int)ceil(($minimum+$halfExtent)/(2*$halfExtent)*$cells)-1;
        $end=(int)floor(($maximum+$halfExtent)/(2*$halfExtent)*$cells);
        return [max(0,min($cells-1,$start)),max(0,min($cells-1,$end))];
    }
}
