<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Format;

use AnthonyMartin\GeoLocation\Circle;
use AnthonyMartin\GeoLocation\Interfaces\{BoundingBoxInterface, GeoPointInterface, PolygonInterface};
use AnthonyMartin\GeoLocation\Exceptions\{InvalidArgumentException, UnsupportedGeometryException};

/** Two-dimensional POINT/POLYGON WKT with longitude as x and latitude as y. */
final class Wkt {
    /** Encode an outer ring, approximating circles with the requested segment count. */
    public static function encode(GeoPointInterface|BoundingBoxInterface|PolygonInterface|Circle $shape, int $segments = 64): string {
        if($shape instanceof Circle || ($shape instanceof BoundingBoxInterface && $shape->getMinLongitude()>$shape->getMaxLongitude())) {
            $shape=GeoJson::decode(GeoJson::encode($shape,$segments));
        }
        if($shape instanceof GeoPointInterface) {return 'POINT ('.self::position(Coordinates::pair($shape)).')';}
        return 'POLYGON (('.implode(', ',array_map(self::position(...),Coordinates::ring($shape,$segments))).'))';
    }

    /** Parse only 2D POINT and a closed, single-ring POLYGON; no SRID, holes or EMPTY. */
    public static function decode(mixed $wkt): GeoPointInterface|PolygonInterface {
        if(!is_string($wkt)) {throw new InvalidArgumentException('WKT must be text.');}
        $wkt=trim($wkt);
        if(preg_match('/^POINT\s*\(([^()]*)\)$/iD',$wkt,$match)) {return Coordinates::point(self::parsePosition($match[1]));}
        if(preg_match('/^POLYGON\s*\(\s*\(([^()]*)\)\s*\)$/iD',$wkt,$match)) {
            return Coordinates::polygon(array_map(self::parsePosition(...),explode(',',$match[1])));
        }
        throw new UnsupportedGeometryException('Only 2D POINT and single-ring POLYGON WKT are supported.');
    }

    /** @param array{float,float} $pair */
    private static function position(array $pair): string {
        // JSON's decimal formatter is locale-independent and round-trips floats.
        return self::number($pair[0]).' '.self::number($pair[1]);
    }
    private static function number(float $number): string {
        $text=json_encode($number,JSON_PRESERVE_ZERO_FRACTION);
        if($text===false) {throw new InvalidArgumentException('WKT coordinates must be finite.');}
        return $text;
    }
    /** @return array{float,float} */
    private static function parsePosition(string $position): array {
        $number='[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?';
        if(!preg_match('/^\s*('.$number.')\s+('.$number.')\s*$/D',$position,$match)) {
            throw new InvalidArgumentException('WKT positions require exactly two finite numbers.');
        }
        return [(float)$match[1],(float)$match[2]];
    }
}
