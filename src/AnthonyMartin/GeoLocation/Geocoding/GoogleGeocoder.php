<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Geocoding;

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Interfaces\{Geocoder, GeoPointInterface};
use AnthonyMartin\GeoLocation\Exceptions\{AddressNotFoundException, InvalidArgumentException, NoApiKeyException, RateLimitedException, UnexpectedResponseException};
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/** Google's forward/reverse adapter with optional injected PSR-18 transport. */
final readonly class GoogleGeocoder implements Geocoder {
    private JsonHttp $http;
    /** @param array<string,string> $components Component filters such as ['country'=>'GB']. */
    public function __construct(private string $apiKey,?ClientInterface $client=null,?RequestFactoryInterface $requestFactory=null,
        private ?string $region=null,private ?string $language=null,private array $components=[]) {
        if($apiKey==='') {throw new NoApiKeyException();}
        foreach($components as $key=>$value) {if(!is_string($key) || !is_string($value) || str_contains($key,':') || str_contains($key,'|') || str_contains($value,'|')) {throw new InvalidArgumentException('Google components must be string filters without separator characters.');}}
        $this->http=new JsonHttp($client,$requestFactory);
    }
    /** Return the first result, including partial matches, or throw when not found. */
    public function geocode(string $address): GeoPointInterface {
        $results=$this->geocodeAll($address);
        if($results===[]) {throw new AddressNotFoundException('Google geocoding found no results (ZERO_RESULTS).');}
        return $results[0]->point;
    }
    /** @return list<GeocodingResult> All validated candidates, or [] on ZERO_RESULTS. */
    public function geocodeAll(string $address): array {return $this->lookup(['address'=>$address]);}
    /** Return Google's first reverse-geocoding result. */
    public function reverse(GeoPointInterface $point): GeocodingResult {
        $point=new GeoPoint($point->getLatitude(),$point->getLongitude());
        $results=$this->lookup(['latlng'=>$point->getLatitude().','.$point->getLongitude()]);
        if($results===[]) {throw new AddressNotFoundException('Google reverse geocoding found no results (ZERO_RESULTS).');}
        return $results[0];
    }
    /** @param array<string,string> $parameters
     * @return list<GeocodingResult> */
    private function lookup(array $parameters): array {
        $parameters['key']=$this->apiKey;
        if($this->region!==null) {$parameters['region']=$this->region;}
        if($this->language!==null) {$parameters['language']=$this->language;}
        if($this->components!==[] && isset($parameters['address'])) {
            $filters=[];foreach($this->components as $key=>$value) {$filters[]=$key.':'.$value;}$parameters['components']=implode('|',$filters);
        }
        $data=$this->http->get('https://maps.googleapis.com/maps/api/geocode/json?'.http_build_query($parameters,'','&',PHP_QUERY_RFC3986));
        if($data===[]) {throw new UnexpectedResponseException('Google geocoding returned invalid JSON.');}
        if(!is_array($data) || !isset($data['status']) || !is_string($data['status'])) {throw new UnexpectedResponseException('Google geocoding response is missing a valid API status.');}
        if($data['status']==='ZERO_RESULTS') {return [];}
        if($data['status']!=='OK') {
            $message='Google geocoding failed with status '.$data['status'].'.';
            if(isset($data['error_message'])&&is_string($data['error_message'])) {$message.=' '.$data['error_message'];}
            if($data['status']==='OVER_QUERY_LIMIT') {throw new RateLimitedException($message);}
            throw new UnexpectedResponseException($message);
        }
        if(!isset($data['results']) || !is_array($data['results']) || !array_is_list($data['results']) || $data['results']===[]) {throw new UnexpectedResponseException('Google geocoding response has no valid first result.');}
        $results=[];
        foreach($data['results'] as $result) {$results[]=$this->result($result);}
        return $results;
    }
    private function result(mixed $result): GeocodingResult {
        if(!is_array($result) || !isset($result['geometry']) || !is_array($result['geometry']) ||
            !isset($result['geometry']['location']) || !is_array($result['geometry']['location'])) {throw new UnexpectedResponseException('Google geocoding response has no valid result location.');}
        $location=$result['geometry']['location'];
        $point=ProviderPoint::from($location['lat']??null,$location['lng']??null);
        $address=$result['formatted_address']??'';$components=$result['address_components']??[];$partial=$result['partial_match']??false;
        if(!is_string($address)||!is_array($components)||!is_bool($partial)) {throw new UnexpectedResponseException('Google geocoding result has malformed address metadata.');}
        return new GeocodingResult($point,$address,$components,$partial,$result);
    }
}
