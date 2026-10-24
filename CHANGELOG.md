# Changelog

## 2.1.2 — 2026-10-23

- Document the 3.0 migration before removing or changing APIs: legacy
  `GeoLocation`/interface and geocoding, mutable point setters, indexed box
  corner assumptions, input-type-preserving coordinate getters, and the
  library base of `InvalidArgumentException`.
- Announce native types and a modern PHP minimum for 3.0. Keep PHP 5.4 support
  and every 2.1.1 runtime behavior on the `2.x` maintenance branch. These are
  docblock and documentation deprecations only, with no runtime warnings.
- Keep incomplete-polygon containment returning `false` and keep constructors
  out of interfaces in both release lines.

## 2.1.1 — 2026-10-23

Version 2.1.0 unintentionally changed public behaviors that worked in 2.0.1.
Version 2.1.1 restores them while retaining the correctness fixes. Users of
2.1.0 should upgrade to 2.1.1.

### Restored compatibility

- Legacy `GeoLocation::getGeocodeFromGoogle()` returns Google's raw, unvalidated
  decoded response, including API error statuses and responses to requests
  without a key. Transport failures and invalid JSON return `null` again.
- `GeoPoint::fromAddress()` transport errors are again catchable as
  `UnexpectedResponseException`; `CurlErrorException` now extends it and keeps
  the descriptive transport error message.
- `BoundingBox::getVertices()` and `getGeoPoints()` restore the 2.0.1 index order:
  SW, SE, NW, NE. Version 2.1.0 unintentionally changed that order. If you build
  polygons from these arrays yourself, use `getPolygon()` or `getPerimeter()`
  for a proper SW, SE, NE, NW ring. New `getSouthWest()`, `getSouthEast()`,
  `getNorthWest()`, and `getNorthEast()` methods provide explicit corner access.
- Remove the constructor requirement from `BoundingBoxInterface`, allowing
  existing subclasses with their own constructors to load again.
- Containment tests for polygons with zero, one, or two vertices return `false`
  instead of throwing; empty polygons no longer emit a warning.
- Coordinate getters preserve validated integer, float, and numeric-string
  input types. Values converted between degrees and radians are floats.
- cURL and JSON are Composer suggestions, needed only for geocoding. Geometry
  installs and runs without them. Missing extensions produce a clear library
  exception in `fromAddress()` and `null` in the legacy geocoder.

### Correctness fixes retained from 2.1.0

- Non-numeric, NaN, infinite, and out-of-range coordinates still throw, as do
  reversed bounding-box latitudes and malformed polygon vertices. These
  previously caused wrong results or silent data corruption.
- Setters validate before changing state and synchronize degrees and radians.
- Haversine distances remain stable for identical and nearby points; the last
  decimals can differ from 2.0.1's spherical-law-of-cosines results.
- Bounding-box polygons remain proper rings. `BoundingBox::contains()` remains
  inclusive, handles antimeridian crossings, and includes zero-distance centers.
  General polygon containment remains planar and half-open; it does not support
  antimeridian-crossing rings.
- BCMath remains unnecessary. Geocoding keeps encoded URL parameters, 5-second
  connection and 10-second total timeouts, and handle cleanup. The modern
  `fromAddress()` API still validates responses. `ExceptionInterface` remains
  the common library catch type.

### Verification

- Add a dedicated 2.0.1 compatibility suite shared by PHPUnit and standalone
  PHP 5.4–7.2 checks. CI covers PHP 5.4–8.5, with additional no-cURL installation
  and geometry checks on PHP 7.4 and 8.5.

## 2.1.0 — 2026-10-23

- Correct bounding-box polygons to SW, SE, NE, NW order and add the documented
  `toPolygon()` alias. See 2.1.1 for restored indexed-corner compatibility.
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
