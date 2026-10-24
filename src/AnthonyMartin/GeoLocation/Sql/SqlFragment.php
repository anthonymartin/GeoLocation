<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Sql;

/** Immutable SQL and its ordered positional or unique named bindings. */
final readonly class SqlFragment {
    /** @param array<int|string, float> $parameters */
    public function __construct(private string $expression, private array $parameters, private bool $postFiltering = false) {}
    /** SQL text to embed in a prepared statement. */
    public function sql(): string { return $this->expression; }
    /** @return array<int|string, float> Bind these values; never interpolate them into SQL. */
    public function bindings(): array { return $this->parameters; }
    /** True for polygon/custom-region envelope filters: test candidates in PHP. */
    public function requiresPostFiltering(): bool { return $this->postFiltering; }
}
