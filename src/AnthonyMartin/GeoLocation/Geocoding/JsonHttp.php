<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Geocoding;

use AnthonyMartin\GeoLocation\Exceptions\{AddressNotFoundException, CurlErrorException, InvalidArgumentException, RateLimitedException, UnexpectedResponseException};
use Psr\Http\Client\{ClientInterface, ClientExceptionInterface};
use Psr\Http\Message\RequestFactoryInterface;

/** @internal Provider HTTP and JSON validation with optional PSR injection. */
final readonly class JsonHttp {
    /** Client and request factory must be supplied together. */
    public function __construct(private ?ClientInterface $client=null,private ?RequestFactoryInterface $requestFactory=null) {
        if(($client===null)!==($requestFactory===null)) {throw new InvalidArgumentException('Inject both a PSR-18 client and a PSR-17 request factory.');}
    }
    /** @param array<string,string> $headers */
    public function get(string $url,array $headers=[],bool $allowNotFound=false): mixed {
        if($this->client===null && !function_exists('curl_init')) {throw new UnexpectedResponseException('Geocoding requires the cURL extension or an injected PSR-18 client.');}
        if(!function_exists('json_decode')) {throw new UnexpectedResponseException('Geocoding requires the JSON extension.');}
        if($this->client!==null && $this->requestFactory!==null) {
            $request=$this->requestFactory->createRequest('GET',$url)->withHeader('Accept','application/json');
            foreach($headers as $name=>$value) {$request=$request->withHeader($name,$value);}
            try {$response=$this->client->sendRequest($request);}
            catch(ClientExceptionInterface $error) {throw new CurlErrorException('Geocoding transport failed: '.$error->getMessage(),0,$error);}
            $status=$response->getStatusCode();$body=(string)$response->getBody();
        } else {
            $response=(new CurlTransport())->request($url,headers:array_map(static fn(string $v): array=>[$v],['Accept'=>'application/json']+$headers));
            $status=$response->status;$body=$response->body;
        }
        if($status===429) {throw new RateLimitedException('Geocoding returned HTTP status 429.');}
        if($status===404 && $allowNotFound) {
            $error=json_decode($body,true);
            if(is_array($error) && isset($error['error']) && is_string($error['error'])) {throw new AddressNotFoundException($error['error']);}
        }
        if($status<200 || $status>=300) {throw new UnexpectedResponseException('Geocoding returned HTTP status '.$status.'.');}
        $data=json_decode($body,true);
        if(json_last_error()!==JSON_ERROR_NONE || !is_array($data)) {throw new UnexpectedResponseException('Geocoding returned invalid JSON.');}
        return $data;
    }
}
