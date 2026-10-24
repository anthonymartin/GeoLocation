<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Sql;

/** SQL syntax only; implementations never access a database. */
interface Dialect {
    /** Quote a validated identifier, optionally qualified by table name. */
    public function quoteIdentifier(string $identifier): string;
    /** Clamp a generated numerical expression to [0, 1]. */
    public function clamp(string $expression): string;
}
