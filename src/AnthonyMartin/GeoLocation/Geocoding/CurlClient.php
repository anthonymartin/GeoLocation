<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Geocoding;

use AnthonyMartin\GeoLocation\Exceptions\{CurlErrorException, InvalidArgumentException, UnexpectedResponseException};
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\{RequestInterface, ResponseInterface, ResponseFactoryInterface, StreamFactoryInterface};

if (!interface_exists(ClientInterface::class) || !interface_exists(ResponseFactoryInterface::class)) {
    throw new UnexpectedResponseException('CurlClient requires psr/http-client and psr/http-factory; install them or use the default native geocoder transport.');
}

/** Optional PSR-18 client with 5-second connection and 10-second total timeouts. */
final readonly class CurlClient implements ClientInterface {
    private StreamFactoryInterface $streamFactory;
    /** Supply PSR-17 response and stream factories; a combined factory needs only one argument. */
    public function __construct(private ResponseFactoryInterface $responseFactory,?StreamFactoryInterface $streamFactory=null) {
        $this->streamFactory=$streamFactory??($responseFactory instanceof StreamFactoryInterface ? $responseFactory : throw new InvalidArgumentException('CurlClient requires a PSR-17 stream factory.'));
    }
    /** Send one request, returning HTTP errors as responses; no redirect following. */
    public function sendRequest(RequestInterface $request): ResponseInterface {
        $uri=$request->getUri();
        if(!in_array($uri->getScheme(),['http','https'],true) || $uri->getHost()==='' || $request->getMethod()==='') {
            throw new RequestException('HTTP request requires an HTTP(S) URI, host and method.',$request);
        }
        try {$body=(string)$request->getBody();}
        catch(\Throwable $error) {throw new RequestException('HTTP request body could not be read.',$request,$error);}
        $headers=[];
        foreach($request->getHeaders() as $name=>$values) {$headers[(string)$name]=array_values($values);}
        try {$native=(new CurlTransport())->request((string)$uri,$request->getMethod(),$headers,$body);}
        catch(CurlErrorException $error) {throw new NetworkException($error->getMessage(),$request,$error);}
        catch(UnexpectedResponseException $error) {throw new RequestException($error->getMessage(),$request,$error);}
        try {
            $response=$this->responseFactory->createResponse($native->status);
            foreach($native->headers as $name=>$values) {$response=$response->withHeader($name,$values);}
            return $response->withBody($this->streamFactory->createStream($native->body));
        } catch(\Throwable $error) {throw new ClientException('HTTP response could not be represented by the supplied factory.',0,$error);}
    }
}
