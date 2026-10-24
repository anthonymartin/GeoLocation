# Changelog

## 2.1.0 — 2026-10-23

- Correct bounding-box polygons to SW, SE, NE, NW order and add the documented
  `toPolygon()` alias. Consumers indexing vertices should note the last two
  corners have exchanged positions.
- Add inclusive `BoundingBox::contains()`, including antimeridian crossings,
  equivalent ±180° longitudes, and zero-distance boxes.
- Accept zero-valued box coordinates; reject non-finite or reversed latitude
  bounds and invalid distances.
- Remove the undeclared BCMath dependency and scientific-notation crashes from
  polygon containment. Invalid vertices and incomplete containment polygons
  now raise meaningful library exceptions.
- Use stable haversine distances in both APIs, fixing NaN self-distances and
  precision loss at short distances.
- Validate coordinates, normalize numeric strings to floats, and synchronize
  degree/radian setters atomically. Invalid setters leave the point unchanged.
- Harden both geocoding APIs with encoded parameters, 5s/10s timeouts, reliable
  connection cleanup, and descriptive transport, HTTP, JSON, and API errors.
  `GeoPoint::fromAddress()` continues to choose Google's first result.
- Publish the previously untagged invalid-unit and interface fixes. Unit names
  are case-insensitive and non-string units are rejected.
- Unify existing library exception inheritance and add `ExceptionInterface` as
  a common catch type. `InvalidUnitException` preserves the global
  `InvalidArgumentException` catch contract.
- Repair and implement the public interfaces; deprecate the legacy GeoLocation
  API without removing it, repair its string output, and initialize its angular
  radius to zero.
- Correct Composer library metadata, declare cURL/JSON requirements, use PSR-4,
  replace ineffective tests, and add regression coverage and CI.
- Preserve PHP >=5.4 runtime support. Modern PHPUnit is a development-only
  dependency; older runtimes have separate compatibility checks.
