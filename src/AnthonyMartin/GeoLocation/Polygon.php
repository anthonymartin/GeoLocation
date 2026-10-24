<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation;

use AnthonyMartin\GeoLocation\Interfaces\GeoPointInterface;
use AnthonyMartin\GeoLocation\Interfaces\PolygonInterface;
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;

/** Immutable perimeter vertices; alternative input points are snapshotted. */
final readonly class Polygon implements PolygonInterface {
    /** Alias for the planar, half-open containment test. */
    public function contains(GeoPointInterface $point): bool { return $this->containsGeoPoint($point); }

    /**
     * Min/max envelope of a planar polygon; not a wrapped antimeridian envelope.
     * Empty polygons have no envelope and throw InvalidArgumentException.
     */
    public function boundingBox(): Interfaces\BoundingBoxInterface {
        if ($this->vertices === []) {
            throw new InvalidArgumentException('An empty polygon has no bounding box.');
        }
        $latitudes = array_map(static fn(GeoPoint $p): float => $p->getLatitude(), $this->vertices);
        $longitudes = array_map(static fn(GeoPoint $p): float => $p->getLongitude(), $this->vertices);
        return new BoundingBox(min($latitudes), min($longitudes), max($latitudes), max($longitudes));
    }

    /** @var list<GeoPoint> */
    private array $vertices;

    public function __construct(GeoPointInterface ...$vertices) {
        $this->vertices = array_values(array_map(
            static fn(GeoPointInterface $point): GeoPoint => $point instanceof GeoPoint
                ? $point : new GeoPoint($point->getLatitude(), $point->getLongitude()),
            $vertices
        ));
    }

    /** Return a new polygon with an appended vertex; the original is unchanged. */
    public function withVertex(GeoPointInterface $point): self {
        return new self(...[...$this->vertices, $point]);
    }

    /** @param array<array{int|float|numeric-string, int|float|numeric-string}> $vertices */
    public static function fromArray(array $vertices): self {
        $points = [];
        foreach ($vertices as $vertex) {
            if (!is_array($vertex) || count($vertex) !== 2 ||
                !array_key_exists(0, $vertex) || !array_key_exists(1, $vertex)) {
                throw new InvalidArgumentException('Each polygon vertex must be a latitude/longitude pair.');
            }
            foreach ($vertex as $coordinate) {
                if (!is_numeric($coordinate) || !is_finite((float) $coordinate)) {
                    throw new InvalidArgumentException('Polygon coordinates must be finite numbers.');
                }
            }
            $points[] = new GeoPoint($vertex[0], $vertex[1]);
        }
        return new self(...$points);
    }

    public function containsGeoPoint(GeoPointInterface $point): bool {
        return self::pointInPolygon($point, $this);
    }

    /*
    * Planar ray casting retains the half-open boundary convention: points on
    * polygon edges are not consistently included. Antimeridian-crossing rings
    * must not be used here; use BoundingBox::contains() for wrapped boxes.
    * @see https://stackoverflow.com/a/18190354
    */
    public static function pointInPolygon(GeoPointInterface $point, PolygonInterface $polygon): bool {
        $vertices = $polygon->getVertices();
        $n = count($vertices);
        if ($n < 3) {
            return false;
        }
        $c = 0;
        $p1 = $vertices[0];
        for ($i=1; $i<=$n; $i++) {
            $p2 = $vertices[$i % $n];
            if (
                    $point->getLongitude() > min($p1->getLongitude(), $p2->getLongitude())

                    && $point->getLongitude() <= max($p1->getLongitude(), $p2->getLongitude())

                    && $point->getLatitude() <= max($p1->getLatitude(), $p2->getLatitude())

                    && $p1->getLongitude() != $p2->getLongitude())
            {

                $xinters = ($point->getLongitude() - $p1->getLongitude()) * ($p2->getLatitude() - $p1->getLatitude()) / ($p2->getLongitude() - $p1->getLongitude()) + $p1->getLatitude();
                if (($p1->getLatitude() == $p2->getLatitude()) ||
                        ($point->getLatitude() <= $xinters)
                ) {
                    $c++;
                }
            }
            $p1 = $p2;
        }
        // if the number of edges we passed through is even, then it's not in the poly.
        return $c%2!=0;
    }


    /** Build in one pass, avoiding repeated withVertex() array copies.
     * @param iterable<GeoPointInterface> $points
     */
    public static function fromGeoPoints(iterable $points): self {
        $vertices = [];
        foreach ($points as $point) { $vertices[] = $point; }
        return new self(...$vertices);
    }

    /** Smaller spherical area in km² or mi², bounded by minor great-circle arcs. */
    public function area(string $unit): float {
        $radius = Earth::getRadius($unit);
        return abs(self::signedArea($this->vectors())) * $radius * $radius;
    }

    /** Closed-ring haversine length in km or mi; incomplete rings are allowed. */
    public function perimeter(string $unit): float {
        Earth::getRadius($unit);
        $length = 0.0;
        $count = count($this->vertices);
        foreach ($this->vertices as $i => $point) {
            $length += $point->distanceTo($this->vertices[($i + 1) % $count], $unit);
        }
        return $length;
    }

    /** Normalized 3D vertex mean, not an area centroid; may lie outside concave rings. */
    public function centroid(): GeoPointInterface {
        $sum = [0.0, 0.0, 0.0];
        foreach ($this->vectors() as $v) {
            for ($i = 0; $i < 3; $i++) { $sum[$i] += $v[$i]; }
        }
        if (self::length($sum) <= count($this->vertices) * 1e-15) {
            throw new InvalidArgumentException('The polygon has no unique vertex-mean centroid.');
        }
        return new GeoPoint(rad2deg(atan2($sum[2], hypot($sum[0], $sum[1]))), rad2deg(atan2($sum[1], $sum[0])));
    }

    /** Planar longitude/latitude orientation; not an antimeridian-aware orientation. */
    public function isClockwise(): bool {
        $sum = 0.0;
        $count = count($this->vertices);
        foreach ($this->vertices as $i => $a) {
            $b = $this->vertices[($i + 1) % $count];
            $sum += self::orientation($this->vertices[0], $a, $b);
        }
        return $sum < 0;
    }

    /** Return the same ring in reverse order. */
    public function reversed(): self { return new self(...array_reverse($this->vertices)); }

    /** Whether this is a simple planar ring with at least three distinct vertices. */
    public function isValid(): bool { return $this->validationErrors() === []; }

    /** Planar checks; spherical validity is a separate caller responsibility. O(n²).
     * @return list<string>
     */
    public function validationErrors(): array {
        $errors = [];
        $count = count($this->vertices);
        $distinct = [];
        $planarArea = 0.0;
        foreach ($this->vertices as $i => $a) {
            $seen = false;
            foreach ($distinct as $other) {
                if ($a->getLatitude() === $other->getLatitude() && $a->getLongitude() === $other->getLongitude()) { $seen = true; break; }
            }
            if (!$seen) { $distinct[] = $a; }
            $b = $this->vertices[($i + 1) % $count];
            $planarArea += self::orientation($this->vertices[0], $a, $b);
            if ($a->getLatitude() === $b->getLatitude() && $a->getLongitude() === $b->getLongitude()) {
                $errors[] = 'Consecutive duplicate vertices at edge ' . $i . '.';
            }
        }
        if (count($distinct) < 3) { $errors[] = 'At least three distinct vertices are required.'; }
        if ($count >= 3 && $planarArea === 0.0) { $errors[] = 'The ring has zero signed planar area.'; }
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($j === $i + 1 || ($i === 0 && $j === $count - 1)) { continue; }
                if (self::segmentsIntersect($this->vertices[$i], $this->vertices[($i + 1) % $count],
                    $this->vertices[$j], $this->vertices[($j + 1) % $count])) {
                    $errors[] = 'Self-intersection between edges ' . $i . ' and ' . $j . '.';
                }
            }
        }
        return $errors;
    }

    /**
     * Inclusive spherical winding test for simple rings smaller than a hemisphere.
     * Edges are minor great-circle arcs. Antipodal edges and hemispheres are ambiguous
     * and throw. Region::contains() deliberately remains planar.
     */
    public function containsSpherical(GeoPointInterface $point): bool {
        if (count($this->vertices) < 3) { return false; }
        $vertices = $this->vectors();
        $area = self::signedArea($vertices);
        if (abs(abs($area) - 2 * M_PI) < 1e-12) {
            throw new InvalidArgumentException('A hemisphere has no unique smaller interior.');
        }
        if ($area === 0.0) { return false; }
        $p = self::vector(new GeoPoint($point->getLatitude(), $point->getLongitude()));
        $winding = 0.0;
        $count = count($vertices);
        foreach ($vertices as $i => $a) {
            $b = $vertices[($i + 1) % $count];
            if (self::segmentDistance($p, $a, $b) <= 1e-12) { return true; }
            $u = self::tangent($a, $p); $v = self::tangent($b, $p);
            $winding += atan2(self::dot($p, self::cross($u, $v)), self::dot($u, $v));
        }
        // The antipodal complementary ring winds with the opposite sign.
        return abs($winding) > M_PI && $winding * $area > 0;
    }

    /**
     * Closed-ring Douglas–Peucker with minor-arc distances in meters. Does not
     * guarantee topology preservation. Returns the original if it would collapse
     * below three vertices; incomplete input and zero tolerance are unchanged.
     */
    public function simplify(float $toleranceMeters): self {
        if (!is_finite($toleranceMeters) || $toleranceMeters < 0) {
            throw new InvalidArgumentException('Simplification tolerance must be finite and nonnegative.');
        }
        $count = count($this->vertices);
        if ($count <= 3 || $toleranceMeters === 0.0) { return $this; }
        $vectors = $this->vectors();
        $vectors[] = $vectors[0];
        $keep = [0 => true, $count => true];
        $stack = [[0, $count]];
        $tolerance = $toleranceMeters / (Earth::RADIUS_KM * 1000);
        while ($stack !== []) {
            [$start, $end] = array_pop($stack);
            $maxDistance = $tolerance;
            $farthest = null;
            for ($i = $start + 1; $i < $end; $i++) {
                $distance = self::segmentDistance($vectors[$i], $vectors[$start], $vectors[$end]);
                if ($distance > $maxDistance) { $maxDistance = $distance; $farthest = $i; }
            }
            if ($farthest !== null) {
                $keep[$farthest] = true;
                $stack[] = [$start, $farthest];
                $stack[] = [$farthest, $end];
            }
        }
        $points = [];
        foreach ($this->vertices as $i => $point) { if (isset($keep[$i])) { $points[] = $point; } }
        return count($points) < 3 ? $this : new self(...$points);
    }

    /** @return list<array{float,float,float}> */
    private function vectors(): array { return array_map(self::vector(...), $this->vertices); }

    /** @return array{float,float,float} */
    private static function vector(GeoPointInterface $p): array {
        $lat = $p->getLatitude(true); $lon = $p->getLongitude(true);
        return [cos($lat) * cos($lon), cos($lat) * sin($lon), sin($lat)];
    }

    /** Signed solid angle from a fan of oriented spherical triangles.
     * @param list<array{float,float,float}> $vertices
     */
    private static function signedArea(array $vertices): float {
        $count = count($vertices);
        if ($count < 3) { return 0.0; }
        foreach ($vertices as $i => $a) {
            if (self::angle($a, $vertices[($i + 1) % $count]) >= M_PI - 1e-12) {
                throw new InvalidArgumentException('Antipodal polygon edges have no unique minor arc.');
            }
        }
        // Pick the best-conditioned fan origin; no coordinate-axis singularity.
        $reference = $vertices[0]; $best = -1.0;
        $sum = [0.0, 0.0, 0.0];
        foreach ($vertices as $v) { for ($i = 0; $i < 3; $i++) { $sum[$i] += $v[$i]; } }
        $candidates = [$reference, [1.0,0.0,0.0], [0.0,1.0,0.0], [0.0,0.0,1.0], [-1.0,0.0,0.0], [0.0,-1.0,0.0], [0.0,0.0,-1.0]];
        $norm = self::length($sum);
        if ($norm > 1e-15) { $candidates[] = [$sum[0]/$norm, $sum[1]/$norm, $sum[2]/$norm]; }
        foreach ($candidates as $candidate) {
            $score = INF;
            foreach ($vertices as $i => $a) {
                $b = $vertices[($i + 1) % $count];
                $score = min($score, hypot(self::determinant($candidate, $a, $b),
                    1 + self::dot($candidate, $a) + self::dot($a, $b) + self::dot($b, $candidate)));
            }
            if ($score > $best) { $best = $score; $reference = $candidate; }
        }
        $area = 0.0;
        foreach ($vertices as $i => $a) {
            $b = $vertices[($i + 1) % $count];
            $area += 2 * atan2(self::determinant($reference, $a, $b),
                1 + self::dot($reference, $a) + self::dot($a, $b) + self::dot($b, $reference));
        }
        $area = fmod($area, 4 * M_PI);
        if ($area > 2 * M_PI) { $area -= 4 * M_PI; }
        if ($area < -2 * M_PI) { $area += 4 * M_PI; }
        return $area;
    }

    /** @param array{float,float,float} $a
     * @param array{float,float,float} $origin
     * @return array{float,float,float}
     */
    private static function tangent(array $a, array $origin): array {
        $difference = [$a[0]-$origin[0], $a[1]-$origin[1], $a[2]-$origin[2]];
        $height = self::dot($difference, $origin);
        return [$difference[0]-$height*$origin[0], $difference[1]-$height*$origin[1], $difference[2]-$height*$origin[2]];
    }

    /** Stable oriented triangle determinant, including very small triangles.
     * @param array{float,float,float} $origin
     * @param array{float,float,float} $a
     * @param array{float,float,float} $b
     */
    private static function determinant(array $origin, array $a, array $b): float {
        return self::dot($origin, self::cross(
            [$a[0]-$origin[0], $a[1]-$origin[1], $a[2]-$origin[2]],
            [$b[0]-$origin[0], $b[1]-$origin[1], $b[2]-$origin[2]]
        ));
    }

    /** @param array{float,float,float} $a
     * @param array{float,float,float} $b */
    private static function dot(array $a, array $b): float { return $a[0]*$b[0] + $a[1]*$b[1] + $a[2]*$b[2]; }

    /** @param array{float,float,float} $a
     * @param array{float,float,float} $b
     * @return array{float,float,float}
     */
    private static function cross(array $a, array $b): array {
        return [$a[1]*$b[2]-$a[2]*$b[1], $a[2]*$b[0]-$a[0]*$b[2], $a[0]*$b[1]-$a[1]*$b[0]];
    }

    /** @param array{float,float,float} $v */
    private static function length(array $v): float { return sqrt(self::dot($v, $v)); }

    /** @param array{float,float,float} $a
     * @param array{float,float,float} $b */
    private static function angle(array $a, array $b): float { return atan2(self::length(self::cross($a, [$b[0]-$a[0], $b[1]-$a[1], $b[2]-$a[2]])), self::dot($a, $b)); }

    /** Angular distance to a minor arc (or its endpoints).
     * @param array{float,float,float} $p
     * @param array{float,float,float} $a
     * @param array{float,float,float} $b
     */
    private static function segmentDistance(array $p, array $a, array $b): float {
        $distance = min(self::angle($p, $a), self::angle($p, $b));
        $normal = self::cross($a, [$b[0]-$a[0], $b[1]-$a[1], $b[2]-$a[2]]);
        $norm = self::length($normal);
        if ($norm < 1e-15) {
            if (self::dot($a, $b) < 0) { throw new InvalidArgumentException('Antipodal segment endpoints have no unique minor arc.'); }
            return $distance;
        }
        $normal = [$normal[0]/$norm, $normal[1]/$norm, $normal[2]/$norm];
        $height = self::dot([$p[0]-$a[0], $p[1]-$a[1], $p[2]-$a[2]], $normal);
        $q = [$p[0]-$height*$normal[0], $p[1]-$height*$normal[1], $p[2]-$height*$normal[2]];
        $length = self::length($q);
        if ($length < 1e-15) { return $distance; }
        $q = [$q[0]/$length, $q[1]/$length, $q[2]/$length];
        if (self::angle($a, $q) + self::angle($q, $b) <= self::angle($a, $b) + 1e-12) {
            $distance = min($distance, asin(min(1.0, abs($height))));
        }
        return $distance;
    }

    private static function segmentsIntersect(GeoPoint $a, GeoPoint $b, GeoPoint $c, GeoPoint $d): bool {
        $abC = self::orientation($a,$b,$c); $abD = self::orientation($a,$b,$d);
        $cdA = self::orientation($c,$d,$a); $cdB = self::orientation($c,$d,$b);
        if (($abC > 0 && $abD < 0 || $abC < 0 && $abD > 0) &&
            ($cdA > 0 && $cdB < 0 || $cdA < 0 && $cdB > 0)) { return true; }
        return ($abC === 0.0 && self::onSegment($a,$b,$c)) || ($abD === 0.0 && self::onSegment($a,$b,$d)) ||
            ($cdA === 0.0 && self::onSegment($c,$d,$a)) || ($cdB === 0.0 && self::onSegment($c,$d,$b));
    }

    private static function orientation(GeoPoint $a, GeoPoint $b, GeoPoint $c): float {
        return ($b->getLongitude()-$a->getLongitude())*($c->getLatitude()-$a->getLatitude()) -
            ($b->getLatitude()-$a->getLatitude())*($c->getLongitude()-$a->getLongitude());
    }

    private static function onSegment(GeoPoint $a, GeoPoint $b, GeoPoint $p): bool {
        return $p->getLongitude() >= min($a->getLongitude(),$b->getLongitude()) &&
            $p->getLongitude() <= max($a->getLongitude(),$b->getLongitude()) &&
            $p->getLatitude() >= min($a->getLatitude(),$b->getLatitude()) &&
            $p->getLatitude() <= max($a->getLatitude(),$b->getLatitude());
    }

    /** @return list<GeoPoint> */
    public function getGeoPoints(): array { return $this->vertices; }

    /** @return list<GeoPoint> */
    public function getVertices(): array { return $this->vertices; }
}
