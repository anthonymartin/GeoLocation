<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation\Geocoding;

use AnthonyMartin\GeoLocation\Exceptions\{CurlErrorException, UnexpectedResponseException};

/** @internal Dependency-free HTTP transport shared with the optional PSR-18 adapter. */
final class CurlTransport {
    /** @param array<string,list<string>> $headers */
    public function request(string $url,string $method='GET',array $headers=[],string $body=''): HttpResponse {
        if(!function_exists('curl_init')) {throw new UnexpectedResponseException('Geocoding requires the cURL extension or an injected PSR-18 client.');}
        $handle=curl_init();
        if($handle===false) {throw new CurlErrorException('Unable to initialize cURL for geocoding.');}
        $responseHeaders=[];$lines=[];
        foreach($headers as $name=>$values) {foreach($values as $value) {$lines[]=$name.': '.$value;}}
        try {
            $options=[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,
                CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$lines,
                CURLOPT_HEADERFUNCTION=>static function($handle,string $line) use (&$responseHeaders): int {
                    if(str_starts_with($line,'HTTP/')) {$responseHeaders=[];}
                    elseif(str_contains($line,':')) {[$name,$value]=explode(':',$line,2);$responseHeaders[strtolower(trim($name))][]=trim($value);}
                    return strlen($line);
                }];
            if($method==='HEAD') {$options[CURLOPT_NOBODY]=true;}
            if($body!=='') {$options[CURLOPT_POSTFIELDS]=$body;}
            foreach($options as $option=>$value) {if(!curl_setopt($handle,$option,$value)) {throw new CurlErrorException('Unable to configure cURL: '.curl_error($handle));}}
            $response=curl_exec($handle);
            $error=$response===false?curl_error($handle):'';
            $status=curl_getinfo($handle,CURLINFO_HTTP_CODE);
        } finally {$handle=null;}
        if($response===false) {throw new CurlErrorException($error!==''?$error:'Geocoding request failed.');}
        if(!is_string($response)) {throw new UnexpectedResponseException('Geocoding transport did not return a response body.');}
        return new HttpResponse($status,$response,$responseHeaders);
    }
}
