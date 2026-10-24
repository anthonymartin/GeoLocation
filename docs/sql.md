# Sql

[README](../README.md) · [Architecture](../ARCHITECTURE.md)

Save each PHP example in your application root, beside `vendor/`, after installing the package.

## SQL store locator

`Sql\NearbyQuery` builds SQL and bindings for MySQL, PostgreSQL and SQLite. It
never opens a connection. Boxes are exact, circles add a haversine radius test,
and polygon/custom-region filters return **bounding-box candidates only**;
`requiresPostFiltering()` tells you to apply the region's `contains()` in PHP.
Column names are validated and quoted, and coordinates are always parameters.

This complete example uses an in-memory SQLite database:

```php
<?php
require 'vendor/autoload.php';

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Sql\NearbyQuery;
use AnthonyMartin\GeoLocation\Sql\SqliteDialect;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE places (id INTEGER PRIMARY KEY, name TEXT, lat REAL, lng REAL)');
$insert = $pdo->prepare('INSERT INTO places VALUES (?, ?, ?, ?)');
$insert->execute([1, 'Downtown', 40.71, -74.01]);
$insert->execute([2, 'Far away', 41.0, -75.0]);
$center = new GeoPoint(40.7, -74.0);
$query = new NearbyQuery(new SqliteDialect(), named: true);
$distance = $query->distanceExpression($center, 'km');
$nearby = $query->within($center->circle(5, 'km'));
$statement = $pdo->prepare('SELECT *, '.$distance->sql().' AS distance_km FROM places WHERE '
    .$nearby->sql().' ORDER BY distance_km LIMIT 10');
$statement->execute(array_merge($distance->bindings(), $nearby->bindings()));
$places = $statement->fetchAll(PDO::FETCH_ASSOC);
assert(count($places) === 1);
```

Use `within($circle->boundingBox())` for a box-only query. Positional `?` bindings
are the default; merge their arrays in SQL occurrence order. Named bindings are
unique across calls on one builder; give separate builders different
`parameterPrefix` values if combining their fragments in one statement. A named
placeholder occurs only once, so native PDO prepares work without emulation.
Both ±180° spellings of the antimeridian are handled.

SQLite must have [math functions enabled](https://www.sqlite.org/lang_mathfunc.html)
(or equivalent functions registered by your application). Set `atan2: true` to
use `2*ATAN2` rather than `2*ASIN`; it still requires SIN, COS, RADIANS, POWER and
SQRT. All variants clamp rounding errors before inverse trigonometry. Store valid,
finite coordinates in your database. Optional integration tests use
`GEOLOCATION_MYSQL_DSN` / `GEOLOCATION_POSTGRES_DSN`, with
`GEOLOCATION_DB_USER` and `GEOLOCATION_DB_PASSWORD` when needed.
