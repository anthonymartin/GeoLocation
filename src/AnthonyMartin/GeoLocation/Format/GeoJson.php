<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Format;

use AnthonyMartin\GeoLocation\{Circle, Earth, GeoPoint};
use AnthonyMartin\GeoLocation\Interfaces\{BoundingBoxInterface, GeoPointInterface, PolygonInterface};
use AnthonyMartin\GeoLocation\Exceptions\{InvalidArgumentException, UnsupportedGeometryException};

/** Two-dimensional GeoJSON codec. Geometry coordinates are always [longitude, latitude]. */
final class GeoJson {
    /** @return array{type: string, coordinates: array<mixed>, bbox: array{float,float,float,float}} */
    public static function encode(GeoPointInterface|BoundingBoxInterface|PolygonInterface|Circle $shape, int $segments = 64): array {
        if ($shape instanceof GeoPointInterface) {
            [$lon,$lat]=Coordinates::pair($shape);
            return ['type'=>'Point','coordinates'=>[$lon,$lat],'bbox'=>[$lon,$lat,$lon,$lat]];
        }
        if ($shape instanceof Circle && $shape->getRadius() === 0.0) { return self::encode($shape->getCenter()); }
        $box=$shape->boundingBox();
        $bbox=[$box->getMinLongitude(),$box->getMinLatitude(),$box->getMaxLongitude(),$box->getMaxLatitude()];
        if ($shape instanceof Circle) {
            $rings=self::circleRings($shape,$segments);
        } elseif ($shape instanceof BoundingBoxInterface && $shape->getMinLongitude()>$shape->getMaxLongitude()) {
            $west=$shape->getMinLongitude();$east=$shape->getMaxLongitude()+360;
            $south=$shape->getMinLatitude();$north=$shape->getMaxLatitude();
            $rings=RingCut::cut([[$west,$south],[$east,$south],[$east,$north],[$west,$north],[$west,$south]]);
        } else { $rings=[Coordinates::ring($shape,$segments)]; }
        if($rings===[]) {throw new UnsupportedGeometryException('Geometry is too small to represent as a nondegenerate ring.');}
        if(count($rings)===1) { return ['type'=>'Polygon','coordinates'=>[$rings[0]],'bbox'=>$bbox]; }
        return ['type'=>'MultiPolygon','coordinates'=>array_map(static fn(array $ring): array => [$ring],$rings),'bbox'=>$bbox];
    }

    /** Encode validated geometry as JSON text, preserving float values. */
    public static function encodeToString(GeoPointInterface|BoundingBoxInterface|PolygonInterface|Circle $shape, int $segments = 64): string {
        $json=json_encode(self::encode($shape,$segments),JSON_PRESERVE_ZERO_FRACTION);
        if($json===false) {throw new InvalidArgumentException('Geometry could not be encoded as JSON.');}
        return $json;
    }

    /** Decode a Point or single-ring Polygon; Feature wrappers and multipart geometries are unsupported. */
    public static function decode(mixed $geojson): GeoPointInterface|PolygonInterface {
        if(is_string($geojson)) {
            $geojson=json_decode($geojson,true);
            if(json_last_error()!==JSON_ERROR_NONE) {throw new InvalidArgumentException('Malformed GeoJSON text: '.json_last_error_msg());}
        }
        if(!is_array($geojson) || !isset($geojson['type']) || !is_string($geojson['type'])) {
            throw new InvalidArgumentException('GeoJSON must be an object with a geometry type.');
        }
        $coordinates=$geojson['coordinates']??null;
        if($geojson['type']==='Point') {return Coordinates::point($coordinates);}
        if($geojson['type']!=='Polygon') {throw new UnsupportedGeometryException('Only Point and single-ring Polygon GeoJSON are supported.');}
        if(!is_array($coordinates) || !array_is_list($coordinates) || $coordinates===[]) {
            throw new InvalidArgumentException('Polygon coordinates must contain one closed ring.');
        }
        if(count($coordinates)!==1) {throw new UnsupportedGeometryException('Polygon holes are not supported.');}
        return Coordinates::polygon($coordinates[0]);
    }
    /** @return list<list<array{float,float}>> */
    private static function circleRings(Circle $circle,int $segments): array {
        if($segments<3 || $segments>10000) {throw new InvalidArgumentException('Circle segments must be between 3 and 10000.');}
        if($circle->getRadius()/Earth::getRadius($circle->getUnit())>=M_PI_2) {
            throw new UnsupportedGeometryException('GeoJSON circle approximations must be smaller than a hemisphere.');
        }
        $center=new GeoPoint($circle->getCenter()->getLatitude(),$circle->getCenter()->getLongitude());
        $ring=[];$previous=$center->getLongitude();
        for($i=0;$i<=$segments;$i++) {
            $point=$center->destination($circle->getRadius(),-$i*360/$segments,$circle->getUnit());
            $lon=$point->getLongitude();
            while($lon-$previous>180) {$lon-=360;}
            while($lon-$previous< -180) {$lon+=360;}
            $ring[]=[$lon,$point->getLatitude()];$previous=$lon;
        }
        $first=$ring[0];$last=$ring[count($ring)-1];
        if(abs($last[0]-$first[0])>180) {
            $pole=$center->getLatitude()>0?90.0:-90.0;
            $ring[]=[$last[0],$pole];$ring[]=[$first[0],$pole];
        }
        $ring[]=$first;
        return RingCut::cut($ring);
    }

}
