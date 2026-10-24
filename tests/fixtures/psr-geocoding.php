<?php

declare(strict_types=1);

namespace GeoLocationTests;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use Nyholm\Psr7\Response;

/** Fixture client: records requests and never opens a socket. */
final class FixtureHttpClient implements ClientInterface {
    public array $requests=[];
    public function __construct(public string $body='', public int $status=200, public ?\Throwable $error=null) {}
    public function sendRequest(RequestInterface $request): ResponseInterface {
        $this->requests[]=$request;
        if($this->error!==null) {throw $this->error;}
        return new Response($this->status,['Content-Type'=>'application/json'],$this->body);
    }
}

/** The executable documentation uses this client for both providers. */
final class DocumentationHttpClient implements ClientInterface {
    public function sendRequest(RequestInterface $request): ResponseInterface {
        if($request->getUri()->getHost()==='maps.googleapis.com') {
            $body='{"status":"OK","results":[{"formatted_address":"Office","geometry":{"location":{"lat":40.5,"lng":-74.4}}}]}';
        } else {
            $result=['lat'=>'40.5','lon'=>'-74.4','display_name'=>'Office','address'=>['city'=>'Example']];
            $body=json_encode(str_ends_with($request->getUri()->getPath(),'/reverse')?$result:[$result],JSON_THROW_ON_ERROR);
        }
        return new Response(200,['Content-Type'=>'application/json'],$body);
    }
}
