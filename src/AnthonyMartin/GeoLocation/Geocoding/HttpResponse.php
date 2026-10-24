<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Geocoding;

/** @internal Native cURL response, independent of optional PSR packages. */
final readonly class HttpResponse {
    /** @param array<string,list<string>> $headers */
    public function __construct(public int $status, public string $body, public array $headers=[]) {}
}
