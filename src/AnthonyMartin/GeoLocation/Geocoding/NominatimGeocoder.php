<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Geocoding;

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Interfaces\{Geocoder, GeoPointInterface};
use AnthonyMartin\GeoLocation\Exceptions\{AddressNotFoundException, InvalidArgumentException, UnexpectedResponseException};
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/** Nominatim adapter. Caller must supply identification and enforce service limits. */
final readonly class NominatimGeocoder implements Geocoder {
    private JsonHttp $http;
    private string $endpoint;
    /** Configure a replaceable endpoint; public OSM service requires at most 1 request/s per application. */
    public function __construct(private string $userAgent,private string $email,?ClientInterface $client=null,?RequestFactoryInterface $requestFactory=null,
        string $endpoint='https://nominatim.openstreetmap.org',private ?string $language=null) {
        if(trim($userAgent)==='' || preg_match('/[\r\n]/',$userAgent) || !preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/D',$email)) {
            throw new InvalidArgumentException('Nominatim requires an application User-Agent and contact email.');
        }
        $parts=parse_url($endpoint);
        if($parts===false || !isset($parts['scheme'],$parts['host']) || !in_array($parts['scheme'],['http','https'],true) ||
            isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Nominatim endpoint must be an HTTP(S) base URL without credentials, query or fragment.');
        }
        $this->endpoint=rtrim($endpoint,'/');$this->http=new JsonHttp($client,$requestFactory);
    }
    /** Return the first candidate's coordinate or throw when no address matches. */
    public function geocode(string $address): GeoPointInterface {
        $results=$this->geocodeAll($address);
        if($results===[]) {throw new AddressNotFoundException('Nominatim found no address results.');}
        return $results[0]->point;
    }
    /** @return list<GeocodingResult> Provider-ranked candidates, without automatic retries or caching. */
    public function geocodeAll(string $address): array {
        $data=$this->request('search',['q'=>$address]);
        if(!is_array($data) || !array_is_list($data)) {throw new UnexpectedResponseException('Nominatim search returned an invalid result list.');}
        return array_map($this->result(...),$data);
    }
    /** Resolve the closest suitable OSM address; it may differ from the requested coordinate. */
    public function reverse(GeoPointInterface $point): GeocodingResult {
        $point=new GeoPoint($point->getLatitude(),$point->getLongitude());
        $data=$this->request('reverse',['lat'=>$point->getLatitude(),'lon'=>$point->getLongitude()]);
        if(is_array($data) && isset($data['error']) && is_string($data['error'])) {throw new AddressNotFoundException('Nominatim reverse lookup: '.$data['error']);}
        return $this->result($data);
    }
    /** @param array<string,string|float> $parameters */
    private function request(string $path,array $parameters): mixed {
        $parameters+=['format'=>'jsonv2','addressdetails'=>1,'email'=>$this->email];
        if($this->language!==null) {$parameters['accept-language']=$this->language;}
        return $this->http->get($this->endpoint.'/'.$path.'?'.http_build_query($parameters,'','&',PHP_QUERY_RFC3986),['User-Agent'=>$this->userAgent],$path==='reverse');
    }
    private function result(mixed $data): GeocodingResult {
        if(!is_array($data) || !isset($data['display_name']) || !is_string($data['display_name']) || (isset($data['address'])&&!is_array($data['address']))) {
            throw new UnexpectedResponseException('Nominatim returned malformed address metadata.');
        }
        $point=ProviderPoint::from($data['lat']??null,$data['lon']??null);
        return new GeocodingResult($point,$data['display_name'],$data['address']??[],false,$data);
    }
}
