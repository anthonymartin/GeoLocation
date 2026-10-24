# Development

[README](../README.md) · [Architecture](../ARCHITECTURE.md)

Run these commands from the root of a repository checkout on PHP 8.3 or later:

```bash
composer install
composer test
composer test:no-bcmath
composer test:readme
composer lint
```

CI tests PHP 8.3, 8.4, and 8.5, including geometry installation without cURL.
PHPStan checks all library source at maximum level without a baseline. README, migration-guide and topic-guide examples
are also executed with mocked geocoding.
Geocoding tests use local response fixtures and do not require an API key.
The `2.x` branch retains its separate PHP 5.4–8.5 compatibility suite.

Run `php benchmarks/run.php 2000` for reproducible local measurements. Optional
MySQL/PostgreSQL tests need the DSNs documented in [SQL](sql.md); SQLite tests run
in the normal suite. Provider tests use fixtures; no external HTTP requests run in CI.

`composer test:readme` parses and executes the actual PHP blocks from README,
UPGRADE-3.0 and every `docs/*.md` file. Only provider transport constructor arguments
are replaced with local fixtures. Examples retain their documented calls and signatures.

## Integration roadmap

Laravel integration belongs in a separate `anthonymartin/laravel-geo-location`
package, built on `Region` and `Sql`. Annual Laravel majors should not force core
releases. Eloquent scopes, casts and validation rules can remain thin adapters.
There is no Laravel dependency or adapter in 3.0.

The 4.0 parking lot is [CDZI-079](https://kanban.cadenzai.com/boards/c0b95186-41f8-4e72-bc7c-4fd4d686b505/tickets/CDZI-079):
remove deprecated Earth statics and the root Google alias; review spherical region
semantics together with their envelopes and SQL/geohash filters. Immutable values
and interface return contracts are already part of 3.0. No 4.0 work is included here.
