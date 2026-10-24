# Benchmarks

Run from a checkout after `composer install`:

```bash
php -d opcache.enable_cli=0 benchmarks/run.php 2000
```

Measured 2026-10-24 on PHP 8.3.35 CLI in Docker, x86_64 Intel Core Ultra 7 270K
Plus, JIT/CLI opcache disabled. Each operation warms up for 100 calls; the table
uses the median of five rounds of 2,000 calls. This is a local microbenchmark,
not a throughput guarantee for an application or a comparison across machines.

| Operation | Microseconds/call | Calls/second |
| --- | ---: | ---: |
| Haversine distance | 0.207 | 4,841,232 |
| Vincenty inverse | 4.351 | 229,810 |
| Box containment | 0.086 | 11,652,771 |
| Circle containment | 0.251 | 3,987,638 |
| Planar polygon containment, 100 vertices | 7.650 | 130,713 |
| Spherical polygon containment, 100 vertices | 374.545 | 2,670 |

Distance cases use Cambridge–Paris; containment cycles through four fixed inside
and outside points. Shapes and points are constructed before timing. The polygon
is a 100-vertex 10 km circle approximation. Spherical containment computes its
oriented area and great-circle winding on each call; it deliberately costs more
than planar ray casting and handles different geometry. No HTTP or database I/O
is included. Karney is not implemented in 3.0, so no Karney number is claimed.

Re-run on your workload before choosing a solver or filter. Use a region envelope
as a candidate filter where its documented geometry matches your region. Do not
use a polygon's planar envelope to filter its opt-in spherical containment.

Building a ring with repeated `withVertex()` copies the growing array and costs
O(n²); prefer the constructor, `fromArray()` or `fromGeoPoints()` for bulk input.
