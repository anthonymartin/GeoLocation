<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Geodesy;

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Interfaces\GeoPointInterface;
use AnthonyMartin\GeoLocation\Exceptions\GeodesicConvergenceException;
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;

/** Vincenty's 1975 nested equations; inverse may not converge near antipodes. */
final readonly class Vincenty implements Geodesic {
    /** Default WGS-84. Accuracy guarantees apply to Earth-like ellipsoids. */
    public function __construct(private Ellipsoid $ellipsoid = new Ellipsoid()) {}

    /** Inverse solution, capped at 200 iterations; never falls back to a sphere. */
    public function inverse(GeoPointInterface $a, GeoPointInterface $b): GeodesicResult {
        $p = new GeoPoint($a->getLatitude(), $a->getLongitude());
        $q = new GeoPoint($b->getLatitude(), $b->getLongitude());
        if ($p->distanceTo($q, 'km') < 1e-12) { return new GeodesicResult(0, 0, 0); }
        // Vincenty's truncated inverse series loses bearing precision near the
        // antipodal caustic even when iteration appears to converge. Keep a
        // conservative 2-degree exclusion for Earth-like ellipsoids.
        if ($p->isAntipodeOf($q, deg2rad(2) * \AnthonyMartin\GeoLocation\Earth::RADIUS_KM * 1000)) {
            throw new GeodesicConvergenceException('Vincenty inverse is unreliable within 2 degrees of the antipode; use a different Geodesic solver.');
        }
        $f = $this->ellipsoid->f;
        $u1 = atan2((1-$f)*sin($p->getLatitude(true)), cos($p->getLatitude(true)));
        $u2 = atan2((1-$f)*sin($q->getLatitude(true)), cos($q->getLatitude(true)));
        $s1 = sin($u1); $c1 = cos($u1); $s2 = sin($u2); $c2 = cos($u2);
        // Preserve tiny latitude differences instead of subtracting two large
        // reduced-latitude angles (important for centimeter-scale endpoints).
        $norm1 = hypot(cos($p->getLatitude(true)), (1-$f)*sin($p->getLatitude(true)));
        $norm2 = hypot(cos($q->getLatitude(true)), (1-$f)*sin($q->getLatitude(true)));
        $sinDeltaU = (1-$f)*sin(deg2rad($q->getLatitude()-$p->getLatitude()))/($norm1*$norm2);
        $longitude = deg2rad(self::longitude($q->getLongitude()-$p->getLongitude()));
        $lambda = $longitude;
        for ($iteration=0; $iteration<200; $iteration++) {
            $sinLambda = sin($lambda); $cosLambda = cos($lambda);
            $x = $c2*$sinLambda; $y = $sinDeltaU + 2*$s1*$c2*sin($lambda/2)**2;
            $sinSigma = hypot($x, $y);
            $cosSigma = $s1*$s2+$c1*$c2*$cosLambda;
            if ($sinSigma < 1e-16) {
                throw new GeodesicConvergenceException('Antipodal inverse solution is not unique.');
            }
            $sigma = atan2($sinSigma, $cosSigma);
            $sinAlpha = $c1*$c2*$sinLambda/$sinSigma;
            $cosSqAlpha = max(0.0, 1-$sinAlpha*$sinAlpha);
            $cos2SigmaM = $cosSqAlpha > 1e-16 ? $cosSigma-2*$s1*$s2/$cosSqAlpha : 0.0;
            $coefficient = $f/16*$cosSqAlpha*(4+$f*(4-3*$cosSqAlpha));
            $next = $longitude+(1-$coefficient)*$f*$sinAlpha*($sigma+$coefficient*$sinSigma*($cos2SigmaM+$coefficient*$cosSigma*(-1+2*$cos2SigmaM**2)));
            if (abs($next-$lambda) <= 1e-15 * max(1e-3, abs($lambda))) {
                [$aa,$bb] = $this->coefficients($cosSqAlpha);
                $distance = $this->ellipsoid->b*$aa*($sigma-self::deltaSigma($bb,$sinSigma,$cosSigma,$cos2SigmaM));
                return new GeodesicResult($distance,
                    self::bearing(rad2deg(atan2($x,$y))),
                    self::bearing(rad2deg(atan2($c1*$sinLambda,$sinDeltaU - 2*$c1*$s2*sin($lambda/2)**2))));
            }
            $lambda = $next;
        }
        throw new GeodesicConvergenceException('Vincenty inverse did not converge after 200 iterations; use a different Geodesic solver.');
    }

    /** Direct solution with normalized longitude and a finite nonnegative distance. */
    public function direct(GeoPointInterface $from, float $distanceMeters, float $bearing): GeoPointInterface {
        if (!is_finite($distanceMeters) || $distanceMeters < 0 || !is_finite($bearing)) {
            throw new InvalidArgumentException('Geodesic distance must be finite and nonnegative; bearing must be finite.');
        }
        $point = new GeoPoint($from->getLatitude(), $from->getLongitude());
        if ($distanceMeters === 0.0) { return $point; }
        $f = $this->ellipsoid->f;
        $alpha1 = deg2rad(self::bearing($bearing));
        $u1 = atan2((1-$f)*sin($point->getLatitude(true)), cos($point->getLatitude(true)));
        $s1 = sin($u1); $c1 = cos($u1);
        $sigma1 = atan2($s1,$c1*cos($alpha1));
        $sinAlpha = $c1*sin($alpha1);
        $cosSqAlpha = max(0.0,1-$sinAlpha**2);
        [$aa,$bb] = $this->coefficients($cosSqAlpha);
        $base = $distanceMeters/($this->ellipsoid->b*$aa);
        if (!is_finite($base)) { throw new InvalidArgumentException('Distance exceeds the solver range.'); }
        $sigma = $base;
        for ($iteration=0; $iteration<200; $iteration++) {
            $cos2SigmaM = cos(2*$sigma1+$sigma);
            $next = $base+self::deltaSigma($bb,sin($sigma),cos($sigma),$cos2SigmaM);
            if (abs($next-$sigma) <= 1e-14) {
                $sigma = $next;
                $sinSigma = sin($sigma); $cosSigma = cos($sigma);
                $cos2SigmaM = cos(2*$sigma1+$sigma);
                $temp = $s1*$sinSigma-$c1*$cosSigma*cos($alpha1);
                $latitude = atan2($s1*$cosSigma+$c1*$sinSigma*cos($alpha1),(1-$f)*hypot($sinAlpha,$temp));
                $lambda = atan2($sinSigma*sin($alpha1),$c1*$cosSigma-$s1*$sinSigma*cos($alpha1));
                $coefficient = $f/16*$cosSqAlpha*(4+$f*(4-3*$cosSqAlpha));
                $longitude = $lambda-(1-$coefficient)*$f*$sinAlpha*($sigma+$coefficient*$sinSigma*($cos2SigmaM+$coefficient*$cosSigma*(-1+2*$cos2SigmaM**2)));
                return new GeoPoint(rad2deg($latitude),self::longitude($point->getLongitude()+rad2deg($longitude)));
            }
            $sigma = $next;
        }
        throw new GeodesicConvergenceException('Vincenty direct did not converge after 200 iterations.');
    }

    /** @return array{float, float} */
    private function coefficients(float $cosSqAlpha): array {
        $ratio = $this->ellipsoid->a/$this->ellipsoid->b;
        $u = $cosSqAlpha*($ratio*$ratio-1);
        return [1+$u/16384*(4096+$u*(-768+$u*(320-175*$u))), $u/1024*(256+$u*(-128+$u*(74-47*$u)))];
    }

    private static function deltaSigma(float $bb, float $sinSigma, float $cosSigma, float $cos2SigmaM): float {
        return $bb*$sinSigma*($cos2SigmaM+$bb/4*($cosSigma*(-1+2*$cos2SigmaM**2)-$bb/6*$cos2SigmaM*(-3+4*$sinSigma**2)*(-3+4*$cos2SigmaM**2)));
    }
    private static function bearing(float $angle): float { return fmod(fmod($angle,360)+360,360); }
    private static function longitude(float $angle): float {
        if ($angle >= -180 && $angle <= 180) { return $angle; }
        return fmod(fmod($angle+180,360)+360,360)-180;
    }
}
