<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Geocoding\Testing;

use AnthonyMartin\GeoLocation\Interfaces\{Geocoder, GeoPointInterface, GeocodingResultInterface};
use AnthonyMartin\GeoLocation\Geocoding\GeocodingResult;
use AnthonyMartin\GeoLocation\Exceptions\AddressNotFoundException;

/** Deterministic in-memory geocoder for application tests; no external calls. */
final readonly class ArrayGeocoder implements Geocoder {
    /** @var array<array-key,list<GeocodingResult>> */
    private array $addresses;
    /** @param array<array-key,GeoPointInterface|GeocodingResultInterface|list<GeocodingResultInterface>> $addresses */
    public function __construct(array $addresses) {
        $copy=[];
        foreach($addresses as $address=>$values) {
            $results=[];
            foreach(is_array($values)?$values:[$values] as $value) {
                $results[]=$value instanceof GeoPointInterface ? new GeocodingResult($value,(string)$address) :
                    new GeocodingResult($value->getPoint(),$value->getFormattedAddress(),$value->getComponents(),$value->isPartialMatch(),$value->getRaw());
            }
            $copy[$address]=$results;
        }
        $this->addresses=$copy;
    }
    /** Return the first configured candidate or throw when absent. */
    public function geocode(string $address): GeoPointInterface {
        $results=$this->geocodeAll($address);
        if($results===[]) {throw new AddressNotFoundException('No in-memory match for address: '.$address);}
        return $results[0]->point;
    }
    /** @return list<GeocodingResult> Exact, case-sensitive address lookup. */
    public function geocodeAll(string $address): array {return $this->addresses[$address]??[];}
    /** Return the first configured result with exactly matching degree coordinates. */
    public function reverse(GeoPointInterface $point): GeocodingResult {
        foreach($this->addresses as $results) {foreach($results as $result) {
            if($result->point->getLatitude()===$point->getLatitude() && $result->point->getLongitude()===$point->getLongitude()) {return $result;}
        }}
        throw new AddressNotFoundException('No in-memory reverse match for this point.');
    }
}
