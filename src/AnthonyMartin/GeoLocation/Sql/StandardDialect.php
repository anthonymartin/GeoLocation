<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Sql;

use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;

/** Shared SQL identifier validation and standard numerical functions. */
abstract class StandardDialect implements Dialect {
    /** Validate each identifier component; arbitrary SQL is never accepted. */
    public function quoteIdentifier(string $identifier): string {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/D', $identifier)) {
            throw new InvalidArgumentException('SQL column names must be identifiers, optionally qualified by table.');
        }
        $quote = $this->quote();
        return implode('.', array_map(static fn(string $part): string => $quote.$part.$quote, explode('.', $identifier)));
    }
    /** Standard SQL clamp using LEAST/GREATEST. */
    public function clamp(string $expression): string { return "LEAST(1.0, GREATEST(0.0, $expression))"; }
    protected function quote(): string { return '"'; }
}
