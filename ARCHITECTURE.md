# Architecture

One package, with dependencies pointing inward.
The root namespace, `Interfaces` and `Exceptions` form the **Core**.
Core geometry is pure and deterministic: no I/O, network, database, or optional extensions.
`Geodesy`, `Format`, `Sql`, `Index`, `Geocoding` and future `Laravel` are outer layers.
Each outer layer may depend on Core, PHP and PSR interfaces, never a sibling layer.
Only Geocoding may depend on HTTP transport facilities.
Production code must never depend on tests or development tools.
Value objects are final and readonly; replacement methods run normal constructors.
Public geometry inputs and contracts use interfaces.
Optional integrations belong in Composer `suggest`, with errors at the point of use.

Run `composer lint`: PHPStan at maximum level, Deptrac, and the Core purity check.
There is no PHPStan baseline; new dependencies must satisfy these rules.
The root GoogleGeocoder is temporarily classified as Geocoding until CDZI-075 moves it.
See [Deptrac configuration](https://deptrac.github.io/deptrac/configuration/) for rule syntax.
