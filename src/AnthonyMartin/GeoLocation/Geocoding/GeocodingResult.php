<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Geocoding;

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Interfaces\{GeoPointInterface, GeocodingResultInterface};
use AnthonyMartin\GeoLocation\Exceptions\InvalidArgumentException;

/** Immutable result snapshot; components/raw contain only JSON-compatible values. */
final readonly class GeocodingResult implements GeocodingResultInterface {
    public GeoPointInterface $point;
    /** @var array<array-key,mixed> */
    public array $components;
    /** @var array<array-key,mixed> */
    public array $raw;
    /** @param array<array-key,mixed> $components
     * @param array<array-key,mixed> $raw */
    public function __construct(GeoPointInterface $point, public string $formattedAddress = '', array $components = [], public bool $partialMatch = false, array $raw = []) {
        $this->point=new GeoPoint($point->getLatitude(),$point->getLongitude());
        $this->components=self::snapshot($components);
        $this->raw=self::snapshot($raw);
    }
    /** Return the immutable coordinate snapshot. */
    public function getPoint(): GeoPointInterface {return $this->point;}
    /** Return the provider's address label. */
    public function getFormattedAddress(): string {return $this->formattedAddress;}
    /** @return array<array-key,mixed> */
    public function getComponents(): array {return $this->components;}
    /** Return the provider's partial-match flag. */
    public function isPartialMatch(): bool {return $this->partialMatch;}
    /** @return array<array-key,mixed> */
    public function getRaw(): array {return $this->raw;}
    /** @param array<array-key,mixed> $values
     * @return array<array-key,mixed> */
    private static function snapshot(array $values,int $depth=0): array {
        if($depth>64) {throw new InvalidArgumentException('Result payload is too deeply nested or recursive.');}
        $copy=[];
        foreach($values as $key=>$value) {
            if(is_array($value)) {$copy[$key]=self::snapshot($value,$depth+1);}
            elseif($value===null || is_string($value) || is_bool($value) || is_int($value) || (is_float($value)&&is_finite($value))) {$copy[$key]=$value;}
            else {throw new InvalidArgumentException('Result payloads must contain only JSON-compatible values.');}
        }
        return $copy;
    }
}
