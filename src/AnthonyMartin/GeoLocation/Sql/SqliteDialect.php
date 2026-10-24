<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Sql;

/** SQLite with its math functions enabled (SQLITE_ENABLE_MATH_FUNCTIONS). */
final class SqliteDialect extends StandardDialect {
    /** SQLite's scalar MIN/MAX replace LEAST/GREATEST. */
    public function clamp(string $expression): string { return "MIN(1.0, MAX(0.0, $expression))"; }
}
