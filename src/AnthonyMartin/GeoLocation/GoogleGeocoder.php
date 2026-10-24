<?php

declare(strict_types=1);

namespace AnthonyMartin\GeoLocation;

use AnthonyMartin\GeoLocation\Exceptions\CurlErrorException;
use AnthonyMartin\GeoLocation\Exceptions\NoApiKeyException;
use AnthonyMartin\GeoLocation\Exceptions\UnexpectedResponseException;

/** Google geocoding adapter. Geometry classes do not depend on this service. */
class GoogleGeocoder implements Interfaces\Geocoder {
  public function __construct(private readonly string $apiKey) {
    if ($apiKey === '') {
      throw new NoApiKeyException();
    }
  }

  /** Resolve Google's first result, including partial matches, or throw. */
  public function geocode(string $address): GeoPoint {
    list($body, $httpStatus) = $this->transport($address);
    if ($httpStatus < 200 || $httpStatus >= 300) {
      throw new UnexpectedResponseException('Google geocoding returned HTTP status ' . $httpStatus . '.');
    }
    $response = json_decode($body);
    if (json_last_error() !== JSON_ERROR_NONE || !is_object($response)) {
      throw new UnexpectedResponseException('Google geocoding returned invalid JSON.');
    }
    if (!isset($response->status) || !is_string($response->status)) {
      throw new UnexpectedResponseException('Google geocoding response is missing a valid API status.');
    }
    if ($response->status === 'ZERO_RESULTS') {
      throw new UnexpectedResponseException('Google geocoding found no results (ZERO_RESULTS).');
    }
    if ($response->status !== 'OK') {
      $message = 'Google geocoding failed with status ' . $response->status . '.';
      if (isset($response->error_message) && is_string($response->error_message)) {
        $message .= ' ' . $response->error_message;
      }
      throw new UnexpectedResponseException($message);
    }
    if (!isset($response->results) || !is_array($response->results) ||
        !isset($response->results[0]) || !is_object($response->results[0]) ||
        !isset($response->results[0]->geometry) || !is_object($response->results[0]->geometry) ||
        !isset($response->results[0]->geometry->location) || !is_object($response->results[0]->geometry->location) ||
        !isset($response->results[0]->geometry->location->lat) ||
        !isset($response->results[0]->geometry->location->lng)) {
      throw new UnexpectedResponseException('Google geocoding response has no valid first result.');
    }

    $location = $response->results[0]->geometry->location;
    if (!is_numeric($location->lat) || !is_numeric($location->lng) ||
        !is_finite((float) $location->lat) || !is_finite((float) $location->lng) ||
        $location->lat < -90 || $location->lat > 90 ||
        $location->lng < -180 || $location->lng > 180) {
      throw new UnexpectedResponseException('Google geocoding first result has invalid coordinates.');
    }

    return new GeoPoint($location->lat, $location->lng);
  }

  /** @return array{string, int} */
  private function transport(string $address): array {
    if (!function_exists('curl_init')) {
      throw new UnexpectedResponseException('Google geocoding requires the cURL extension.');
    }
    if (!function_exists('json_decode')) {
      throw new UnexpectedResponseException('Google geocoding requires the JSON extension.');
    }
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' .
      rawurlencode($address) . '&key=' . rawurlencode($this->apiKey);
    $handle = curl_init();
    if ($handle === false) {
      throw new CurlErrorException('Unable to initialize cURL for Google geocoding.');
    }

    try {
      $options = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10
      );
      foreach ($options as $option => $value) {
        if (!curl_setopt($handle, $option, $value)) {
          throw new CurlErrorException('Unable to configure cURL for Google geocoding: ' . curl_error($handle));
        }
      }
      $body = curl_exec($handle);
      $error = ($body === false) ? curl_error($handle) : '';
      $httpStatus = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    } finally {
      // PHP 8 releases CurlHandle when its final reference is dropped.
      // curl_close() is a deprecated no-op on PHP 8.5.
      $handle = null;
    }

    if ($body === false) {
      throw new CurlErrorException($error !== '' ? $error : 'Google geocoding request failed.');
    }
    if (!is_string($body)) {
      throw new UnexpectedResponseException('Google geocoding transport did not return a response body.');
    }
    return [$body, $httpStatus];
  }

}
