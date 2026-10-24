<?php

declare(strict_types=1);

require_once __DIR__.'/fixtures/psr-geocoding.php';

use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Geocoding\{CurlClient, GeocodingResult, GoogleGeocoder, NominatimGeocoder, NetworkException, RequestException};
use AnthonyMartin\GeoLocation\Geocoding\Testing\ArrayGeocoder;
use AnthonyMartin\GeoLocation\Exceptions\{AddressNotFoundException, CurlErrorException, InvalidArgumentException, RateLimitedException, UnexpectedResponseException};
use GeoLocationTests\FixtureHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\TestCase;

final class GeocodingPsrTest extends TestCase {
    private const GOOGLE='{"status":"OK","results":[{"formatted_address":"Office","partial_match":true,"address_components":[{"long_name":"New York","types":["locality"]}],"geometry":{"location":{"lat":40.5,"lng":-74.4}}},{"formatted_address":"Other","geometry":{"location":{"lat":41,"lng":-73}}}]}';
    private const NOMINATIM='{"lat":"51.5","lon":"-0.12","display_name":"London","address":{"city":"London"},"licence":"Data © OpenStreetMap contributors, ODbL 1.0."}';

    public function testGoogleCandidatesReverseOptionsAndAlias(): void {
        $client=new FixtureHttpClient(self::GOOGLE);$factory=new Psr17Factory();
        $g=new GoogleGeocoder('key&reserved=?',client:$client,requestFactory:$factory,region:'gb',language:'en',components:['country'=>'GB']);
        $all=$g->geocodeAll('Office & annex');self::assertCount(2,$all);
        self::assertSame('Office',$all[0]->formattedAddress);self::assertTrue($all[0]->partialMatch);
        self::assertSame('New York',$all[0]->components[0]['long_name']);self::assertArrayHasKey('geometry',$all[0]->raw);
        self::assertSame(40.5,$g->geocode('Office')->getLatitude());
        self::assertSame('Office',$g->reverse(new GeoPoint(40,-74))->formattedAddress);
        parse_str($client->requests[0]->getUri()->getQuery(),$query);
        self::assertSame(['address'=>'Office & annex','key'=>'key&reserved=?','region'=>'gb','language'=>'en','components'=>'country:GB'],$query);
        parse_str($client->requests[2]->getUri()->getQuery(),$reverse);
        self::assertSame('40,-74',$reverse['latlng']);self::assertArrayNotHasKey('components',$reverse);
        $alias=new \AnthonyMartin\GeoLocation\GoogleGeocoder('key',$client,$factory);
        self::assertInstanceOf(GoogleGeocoder::class,$alias);self::assertSame(40.5,$alias->geocode('Office')->getLatitude());
    }

    public function testNominatimIdentifiesApplicationAndSupportsReplaceableEndpoint(): void {
        $client=new FixtureHttpClient('['.self::NOMINATIM.']');
        $g=new NominatimGeocoder('ExampleApp/1.0','owner@example.com',$client,new Psr17Factory(),endpoint:'https://example.test/nominatim/',language:'en');
        self::assertSame(51.5,$g->geocode('London')->getLatitude());
        $all=$g->geocodeAll('London');self::assertSame('London',$all[0]->getFormattedAddress());
        self::assertFalse($all[0]->isPartialMatch());self::assertSame(['city'=>'London'],$all[0]->getComponents());
        self::assertStringContainsString('OpenStreetMap',$all[0]->getRaw()['licence']);
        $request=$client->requests[0];self::assertSame('ExampleApp/1.0',$request->getHeaderLine('User-Agent'));
        self::assertSame('/nominatim/search',$request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(),$query);self::assertSame('owner@example.com',$query['email']);self::assertSame('jsonv2',$query['format']);self::assertSame('en',$query['accept-language']);
        $client->body=self::NOMINATIM;
        self::assertSame('London',$g->reverse(new GeoPoint(51.5,-.12))->formattedAddress);
        self::assertSame('/nominatim/reverse',$client->requests[2]->getUri()->getPath());
    }

    public function testNotFoundRateLimitAndMalformedProviderResponses(): void {
        $client=new FixtureHttpClient('{"status":"ZERO_RESULTS","results":[]}');$g=new GoogleGeocoder('key',$client,new Psr17Factory());
        self::assertSame([],$g->geocodeAll('missing'));
        try {$g->geocode('missing');self::fail();}catch(AddressNotFoundException){self::assertTrue(true);}
        foreach(['{"status":"OVER_QUERY_LIMIT"}','{"status":"OK","results":[{"geometry":{"location":{"lat":null,"lng":0}}}]}','{}','{','{"status":"OK","results":[{"geometry":true}]}'] as $body) {
            $client->body=$body;
            try {$g->geocodeAll('Office');self::fail();}catch(UnexpectedResponseException){self::assertTrue(true);}
        }
        $client->status=429;$client->body='not json';
        try {$g->geocode('Office');self::fail();}catch(RateLimitedException){self::assertTrue(true);}
        $client->status=200;$client->body='[]';$n=new NominatimGeocoder('App/1','you@example.com',$client,new Psr17Factory());
        self::assertSame([],$n->geocodeAll('missing'));
        try {$n->geocode('missing');self::fail();}catch(AddressNotFoundException){self::assertTrue(true);}
        $client->status=404;$client->body='{"error":"Unable to geocode"}';
        try {$n->reverse(new GeoPoint(0,0));self::fail();}catch(AddressNotFoundException){self::assertTrue(true);}
        $client->status=200;
        foreach(['{}','true','{"lat":"bad","lon":0,"display_name":"Bad"}','{"lat":0,"lon":0,"display_name":false}'] as $body) {
            $client->body='['.$body.']';
            try {$n->geocode('bad');self::fail();}catch(UnexpectedResponseException){self::assertTrue(true);}
        }
    }

    public function testInjectedNetworkFailuresRetainLibraryExceptionFamily(): void {
        $request=new Request('GET','https://example.test');
        $error=new NetworkException('timeout',$request);
        $client=new FixtureHttpClient(error:$error);
        try {(new GoogleGeocoder('key',$client,new Psr17Factory()))->geocode('Office');self::fail();}
        catch(CurlErrorException $caught) {self::assertSame($error,$caught->getPrevious());self::assertStringContainsString('timeout',$caught->getMessage());}
    }

    public function testImmutableResultsAndInMemoryGeocoder(): void {
        $nested=['city'=>'London'];$raw=['address'=>&$nested];
        $result=new GeocodingResult(new GeoPoint(51.5,-.12),'London',$nested,true,$raw);
        $nested['city']='changed';self::assertSame('London',$result->raw['address']['city']);self::assertSame('London',$result->components['city']);
        $array=new ArrayGeocoder(['Office'=>$result,'Other'=>new GeoPoint(1,2)]);
        self::assertSame(51.5,$array->geocode('Office')->getLatitude());self::assertCount(1,$array->geocodeAll('Other'));
        self::assertSame('London',$array->reverse(new GeoPoint(51.5,-.12))->formattedAddress);self::assertSame([],$array->geocodeAll('missing'));
        try {$array->reverse(new GeoPoint(0,0));self::fail();}catch(AddressNotFoundException){self::assertTrue(true);}
        foreach([fn()=>new NominatimGeocoder('', 'x@y.test'),fn()=>new NominatimGeocoder("App\r\nInjected",'x@y.test'),fn()=>new NominatimGeocoder('App','bad'),
            fn()=>new NominatimGeocoder('App','x@y.test',endpoint:'file:///tmp/x'),fn()=>new GoogleGeocoder('key',new FixtureHttpClient()),
            fn()=>new GeocodingResult(new GeoPoint(0,0),raw:['object'=>new stdClass()])] as $call) {
            try {$call();self::fail();}catch(InvalidArgumentException){self::assertTrue(true);}
        }
    }

    public function testCurlClientPreservesHttpErrorsAndPsrExceptionTypes(): void {
        require_once __DIR__.'/fixtures/native-curl-transport.php';
        \AnthonyMartin\GeoLocation\Geocoding\GeocodingCurlFixture::reset();
        \AnthonyMartin\GeoLocation\Geocoding\GeocodingCurlFixture::$httpStatus=429;
        \AnthonyMartin\GeoLocation\Geocoding\GeocodingCurlFixture::$body='rate limited';
        $client=new CurlClient(new Psr17Factory());$request=new Request('GET','https://example.test');
        \AnthonyMartin\GeoLocation\Geocoding\GeocodingCurlFixture::$headerLines=["HTTP/1.1 100 Continue\r\n","X-Discard: yes\r\n","HTTP/1.1 429 Too Many Requests\r\n","X-Test: one\r\n","X-Test: two\r\n"];
        $response=$client->sendRequest($request);
        self::assertSame(429,$response->getStatusCode());
        self::assertSame('rate limited',$response->getBody()->getContents());
        self::assertSame(['one','two'],$response->getHeader('X-Test'));
        self::assertFalse($response->hasHeader('X-Discard'));
        \AnthonyMartin\GeoLocation\Geocoding\GeocodingCurlFixture::$body=false;
        try {$client->sendRequest($request);self::fail();}catch(\Psr\Http\Client\NetworkExceptionInterface $error){self::assertSame($request,$error->getRequest());}
        try {$client->sendRequest(new Request('GET','file:///tmp/file'));self::fail();}catch(\Psr\Http\Client\RequestExceptionInterface){self::assertTrue(true);}
    }
}
