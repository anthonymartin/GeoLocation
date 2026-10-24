<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Format;

use AnthonyMartin\GeoLocation\{Circle, GeoPoint, Polygon};
use AnthonyMartin\GeoLocation\Interfaces\{BoundingBoxInterface, GeoPointInterface, PolygonInterface};
use AnthonyMartin\GeoLocation\Exceptions\{InvalidArgumentException, UnsupportedGeometryException};

/** @internal Shared validated coordinate conversion for the Format layer. */
final class Coordinates {
    /** @return array{float, float} Longitude, latitude. */
    public static function pair(GeoPointInterface $point): array {
        $validated=new GeoPoint($point->getLatitude(),$point->getLongitude());
        return [$validated->getLongitude(),$validated->getLatitude()];
    }

    /** Decode exactly two JSON numbers; numeric strings and third ordinates are rejected. */
    public static function point(mixed $position): GeoPointInterface {
        if (!is_array($position) || !array_is_list($position) || count($position)!==2 ||
            (!is_int($position[0]) && !is_float($position[0])) || (!is_int($position[1]) && !is_float($position[1]))) {
            throw new InvalidArgumentException('A position must contain exactly two numbers: longitude, latitude.');
        }
        return new GeoPoint($position[1],$position[0]);
    }

    /** Decode a closed ring and discard its repeated endpoint. */
    public static function polygon(mixed $ring): PolygonInterface {
        if (!is_array($ring) || !array_is_list($ring) || count($ring)<4) {
            throw new InvalidArgumentException('A polygon ring needs at least four positions including closure.');
        }
        $points=array_map(self::point(...),$ring);
        $first=$points[0];$last=$points[count($points)-1];
        if ($first->getLatitude()!==$last->getLatitude() || $first->getLongitude()!==$last->getLongitude()) {
            throw new InvalidArgumentException('Polygon ring must close with its first position.');
        }
        array_pop($points);
        return new Polygon(...$points);
    }

    /** @return non-empty-list<array{float, float}> Closed ring, counterclockwise in the longitude/latitude plane. */
    public static function ring(BoundingBoxInterface|PolygonInterface|Circle $shape, int $segments): array {
        if ($shape instanceof Circle) {
            if ($segments<3 || $segments>10000) {throw new InvalidArgumentException('Circle segments must be between 3 and 10000.');}
            $center=new GeoPoint($shape->getCenter()->getLatitude(),$shape->getCenter()->getLongitude());
            $points=[];
            for($i=0;$i<$segments;$i++) {$points[]=$center->destination($shape->getRadius(),-$i*360/$segments,$shape->getUnit());}
        } else {$points=$shape instanceof BoundingBoxInterface ? $shape->getPerimeter() : $shape->getVertices();}
        if(count($points)<3) {throw new UnsupportedGeometryException('At least three vertices are needed to encode a polygon.');}
        $ring=array_map(self::pair(...),$points);
        if($ring[0]===$ring[count($ring)-1]) {array_pop($ring);}
        if(count($ring)<3) {throw new UnsupportedGeometryException('At least three vertices are needed to encode a polygon.');}
        $area=0.0;$n=count($ring);
        for($i=0;$i<$n;$i++) {$j=($i+1)%$n;$area+=$ring[$i][0]*$ring[$j][1]-$ring[$j][0]*$ring[$i][1];}
        if($area<0) {$ring=array_reverse($ring);}
        $ring[]=$ring[0];
        return $ring;
    }
}
