<?php

declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Geodesy\Vincenty;
use AnthonyMartin\GeoLocation\Polygon;

$iterations = filter_var($argv[1] ?? '10000', FILTER_VALIDATE_INT);
if ($iterations === false || $iterations < 1 || $iterations > 1000000) {
    fwrite(STDERR, "Usage: php benchmarks/run.php [iterations: 1..1000000]\n"); exit(1);
}
$start = new GeoPoint(52.205, 0.119);
$end = new GeoPoint(48.857, 2.351);
$solver = new Vincenty();
$box = $start->boundingBox(10, 'km');
$circle = $start->circle(10, 'km');
$points = [];
for ($i=0;$i<100;$i++) { $points[]=$start->destination(10,$i*3.6,'km'); }
$polygon = Polygon::fromGeoPoints($points);
$queries = [$start,$start->destination(9,45,'km'),$start->destination(11,45,'km'),$end];
$cases = [
    'haversine distance' => static fn(int $i): float => $start->distanceTo($end,'km'),
    'Vincenty inverse' => static fn(int $i): float => $solver->inverse($start,$end)->distanceMeters,
    'box contains' => static fn(int $i): bool => $box->contains($queries[$i%4]),
    'circle contains' => static fn(int $i): bool => $circle->contains($queries[$i%4]),
    'polygon planar contains (100 vertices)' => static fn(int $i): bool => $polygon->contains($queries[$i%4]),
    'polygon spherical contains (100 vertices)' => static fn(int $i): bool => $polygon->containsSpherical($queries[$i%4]),
];
$opcache = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
$jit = is_array($opcache) && ($opcache['jit']['on'] ?? false) ? 'on' : 'off';
printf("PHP %s; %s; %d iterations; median of 5 rounds; JIT=%s\n", PHP_VERSION, php_uname('m'), $iterations, $jit);
printf("%-45s %12s %12s\n", 'Operation', 'microsec/op', 'ops/sec');
foreach ($cases as $label => $operation) {
    for ($i=0;$i<100;$i++) { $operation($i); }
    $samples=[];$checksum=0.0;
    for ($round=0;$round<5;$round++) {
        $time=hrtime(true);
        for ($i=0;$i<$iterations;$i++) { $checksum+=(float)$operation($i); }
        $samples[]=(hrtime(true)-$time)/$iterations/1000;
    }
    sort($samples);
    printf("%-45s %12.3f %12.0f\n",$label,$samples[2],1000000/$samples[2]);
    if (!is_finite($checksum)) { throw new RuntimeException('Invalid benchmark result'); }
}
echo "Karney: not implemented in 3.0. No HTTP or database I/O is benchmarked.\n";
