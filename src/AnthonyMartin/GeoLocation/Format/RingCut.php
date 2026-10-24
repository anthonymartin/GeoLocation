<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Format;

/** @internal Clip an unwrapped ring into longitude strips for RFC 7946 maps. */
final class RingCut {
    /** @param non-empty-list<array{float,float}> $ring
     * @return list<list<array{float,float}>> */
    public static function cut(array $ring): array {
        $longitudes=array_column($ring,0);
        $min=(int)floor((min($longitudes)+180)/360);
        $max=(int)floor((max($longitudes)+180)/360);
        $result=[];
        for($strip=$min;$strip<=$max;$strip++) {
            $clipped=self::clip(self::clip($ring,-180+360*$strip,true),180+360*$strip,false);
            if(count($clipped)<3) {continue;}
            $pairs=array_map(static fn(array $p): array => [max(-180.0,min(180.0,$p[0]-360*$strip)),$p[1]],$clipped);
            if($pairs[0]!==$pairs[count($pairs)-1]) {$pairs[]=$pairs[0];}
            $area=0.0;
            for($i=0;$i<count($pairs)-1;$i++) {$area+=$pairs[$i][0]*$pairs[$i+1][1]-$pairs[$i+1][0]*$pairs[$i][1];}
            if($area===0.0) {continue;}
            if($area<0) {$pairs=array_reverse($pairs);}
            $result[]=$pairs;
        }
        return $result;
    }

    /** @param list<array{float,float}> $ring
     * @return list<array{float,float}> */
    private static function clip(array $ring,float $edge,bool $lower): array {
        if($ring===[]) {return [];}
        $out=[];$previous=$ring[count($ring)-1];
        $previousInside=$lower?$previous[0]>=$edge:$previous[0]<=$edge;
        foreach($ring as $point) {
            $inside=$lower?$point[0]>=$edge:$point[0]<=$edge;
            if($inside!==$previousInside) {
                $fraction=($edge-$previous[0])/($point[0]-$previous[0]);
                $out[]=[$edge,$previous[1]+$fraction*($point[1]-$previous[1])];
            }
            if($inside) {$out[]=$point;}
            $previous=$point;$previousInside=$inside;
        }
        // Remove repeated consecutive clip intersections.
        $clean=[];
        foreach($out as $p) {if($clean===[] || $clean[count($clean)-1]!==$p) {$clean[]=$p;}}
        return $clean;
    }
}
