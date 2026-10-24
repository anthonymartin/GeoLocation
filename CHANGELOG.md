# Changelog

## 3.0.0 — 2026-10-24

An earlier GitHub-only v3.0.0 tag was replaced before Packagist publication.

This major release changes public contracts intentionally. Read
[UPGRADE-3.0.md](UPGRADE-3.0.md) before changing your Composer constraint.
Applications using `^2.0` remain on 2.x.

### Breaking changes

- Require PHP 8.3 or later; add native parameter/return types and precise numeric
  input and collection annotations.
- Make `GeoPoint` final and readonly with private scalar coordinate fields.
  Replace setters with `withLatitude()` and `withLongitude()`, which construct
  new validated points. Use composition or `GeoPointInterface` for custom behavior.
- Make `Polygon` final and readonly. Replace mutating `addVertex()` with
  `withVertex()`, returning a new polygon. Snapshot alternative point inputs.
- Replace `Interfaces\Polygon` with `PolygonInterface`. Geometry consumes point
  and polygon interfaces; interface factories return `self` and other geometry
  results use interfaces. Implementations can supply their own geometry types.
- Remove `GeoLocation`, `Interfaces\GeoLocationInterface`, `GeoPoint::fromAddress()`
  and the legacy raw geocoder. Use `(new GoogleGeocoder($apiKey))->geocode($address)`.
  `GoogleGeocoder` implements the provider-independent `Interfaces\Geocoder` contract.
- Normalize validated coordinates to floats, including all point and box getters.
- Return SW, SE, NE, NW perimeter order from box `getVertices()` and `getGeoPoints()`.
  Named corner getters retain their meanings. Align interface parameter names with
  implementations for named-argument calls.
- Make the library's `InvalidArgumentException` extend PHP's native argument
  exception and `InvalidUnitException` extend the library argument exception.
  `ExceptionInterface` remains the common catch type.
- Initialize Earth's existing writable latitude/longitude bounds in radians
  instead of null; type them as floats. Deprecate these writable properties for
  removal in 4.0 in favor of the bound getters. Add typed radius and WGS-84 constants.

### Core geometry

- Add `Region` with `contains()` and `boundingBox()`, implemented by boxes,
  polygons and immutable `Circle`; expose `GeoPoint::circle()`.
- Add spherical bearings, destinations, midpoints, interpolation and antipode
  detection, with explicit errors for undefined coincident/antipodal bearings.
- Add polygon spherical area, closed perimeter, vertex-mean centroid, bulk iterable
  construction, planar validity/orientation, immutable reversal and meter-based
  simplification. `PolygonInterface` exposes the new contracts.
- Add opt-in spherical winding containment for simple minor-arc rings, including
  poles and the antimeridian. Existing planar containment/envelopes are unchanged.
  Ambiguous antipodal edges and hemispheres throw; spherical area is not ellipsoidal.

### Geodesy

- Add `Geodesic`, `Ellipsoid`, `GeodesicResult` and a bounded Vincenty direct/inverse
  solver, with WGS-84/GRS80 definitions and exact international-mile conversion.
- Verify 1,000 published reference cases. Inverse solutions reject points within
  2° of the antipode and throw on nonconvergence; there is no silent fallback.
  Karney is not included in this release.

### SQL, formats and indexing

- Add parameterized MySQL/PostgreSQL/SQLite proximity fragments, safe identifier
  quoting, named or positional bindings, clamped haversine expressions and explicit
  candidate-only flags for polygon/custom-region bounding filters. No database I/O.
- Add GeoJSON and WKT point/single-ring polygon codecs with explicit coordinate
  order and validation. GeoJSON can encode wrapped boxes and approximated circles
  as MultiPolygon for map clients; multipart decoding, holes and altitude throw.
- Add 1–12-character geohashes, cell centers/boxes, conventional grid neighbors,
  radius precision selection, and bounded coverage with pre-allocation size checks.

### Geocoding

- Move Google into `Geocoding` and add Nominatim, reverse geocoding, multiple
  candidates, immutable result metadata and provider-independent interfaces.
- Support optional PSR-18 clients and PSR-17 factories, a native cURL fallback,
  a PSR-compatible `CurlClient`, and the in-memory `Testing\ArrayGeocoder`.
- Surface not-found and rate-limit exceptions, validate provider payloads,
  preserve transport exception inheritance, encode parameters and enforce native
  5-second connection / 10-second total timeouts. Injected clients own their limits.
- Deprecate the root `GoogleGeocoder` compatibility alias for removal in 4.0.
  The other 3.x deprecation is Earth's writable bound statics; neither affects 2.x.

### Validation and documentation

- Enforce inward layer dependencies with Deptrac, pure geometry/outer math with
  AST checks, and PHPStan at maximum level without a baseline.
- Split README into five core examples and linked topic guides. Update migration
  signatures and execute all README, migration and topic-guide PHP examples in CI.
- Add reproducible benchmarks for haversine, Vincenty and shape containment,
  recording the measured environment and avoiding a nonexistent Karney comparison.
- Test PHP 8.3–8.5, including clean production installs without cURL/PSR packages.
  Keep all applicable regression coverage; the superseded unpublished draft does
  not impose a separate 3.0-to-3.1 compatibility contract.
- Record Laravel as a future separate integration package and park the two
  deprecated APIs and spherical Region default discussion for 4.0.

### Retained behavior and support

- Incomplete polygons still return `false`. Interfaces do not declare constructors.
- Keep strict validation, haversine distances, inclusive/antimeridian-aware box
  containment, proper polygon rings, and no BCMath dependency.
- Geocoding validates Google's first result, encodes URL parameters, uses
  5-second connection/10-second total timeouts, and releases handles on failures.
  Geometry has no cURL dependency; cURL remains a suggestion.
- Maintain `2.x` for PHP 5.4 users and applications not ready to migrate, with
  security and bug fixes alongside 3.x and no scheduled end-of-support date.
  Its compatibility suite remains on the maintenance branch.

## 2.1.3 — 2026-10-24

- Keep 2.x supported alongside 3.x with no scheduled end-of-support date.
  This supersedes the 12-month period announced in 2.1.2. Upgrading to 3.0
  is optional; `^2.0` continues to receive compatible 2.x releases.
- Remove API deprecation annotations, including the legacy class annotation.
  Existing 2.x APIs remain supported as-is; migration guidance applies only to
  applications choosing 3.0. No runtime behavior changes.

## 2.1.2 — 2026-10-23

- Document the 3.0 migration before removing or changing APIs: legacy
  `GeoLocation`/interface and geocoding, mutable point setters, indexed box
  corner assumptions, input-type-preserving coordinate getters, and the
  library base of `InvalidArgumentException`.
- Announce native types and a PHP 8.3 minimum for 3.0. Keep PHP 5.4 support
  and every 2.1.1 runtime behavior on the `2.x` maintenance branch. These are
  docblock and documentation deprecations only, with no runtime warnings.
- Maintain 2.x for security and bug fixes for 12 months after 3.0.0 ships.
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
