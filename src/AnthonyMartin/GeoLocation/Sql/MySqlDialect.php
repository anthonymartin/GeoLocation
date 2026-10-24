<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Sql;

/** MySQL syntax, including backtick-quoted identifiers. */
final class MySqlDialect extends StandardDialect {
    protected function quote(): string { return '`'; }
}
