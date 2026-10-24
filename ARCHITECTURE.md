# Architecture

One package, with dependencies pointing inward.
The root namespace, `Interfaces` and `Exceptions` form the **Core**.
Core geometry is pure and deterministic: no I/O, network, database, or optional extensions.
`Geodesy`, `Format`, `Sql`, `Index`, `Geocoding` and future `Laravel` are outer layers.
Each outer layer may depend on Core, PHP and PSR interfaces, never a sibling layer.
Only Geocoding may depend on HTTP transport facilities; function-call purity checks also cover Geodesy, Format, Sql and Index.
Production code must never depend on tests or development tools.
GeoPoint, Polygon and Circle are final and readonly; replacements run normal constructors.
BoundingBox remains subclassable, without public mutation methods.
Public geometry inputs and contracts use interfaces.
Optional integrations belong in Composer `suggest`, with errors at the point of use.

Run `composer lint`: PHPStan at maximum level, Deptrac, and the Core purity check.
There is no PHPStan baseline; new dependencies must satisfy these rules.
The root GoogleGeocoder file is a verified class alias only; the actual adapter lives in Geocoding.
See [Deptrac configuration](https://deptrac.github.io/deptrac/configuration/) for rule syntax.
Geodesy stays an outer pure-math layer; use `Vincenty::inverse()` explicitly (CDZI-072 option a).

Future Laravel integration will be a separate package built on Region and Sql; see [Development](docs/development.md).
