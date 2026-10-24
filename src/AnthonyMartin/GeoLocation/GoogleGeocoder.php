<?php

namespace AnthonyMartin\GeoLocation;

use AnthonyMartin\GeoLocation\Exceptions\CurlErrorException;
use AnthonyMartin\GeoLocation\Exceptions\NoApiKeyException;
use AnthonyMartin\GeoLocation\Exceptions\UnexpectedResponseException;

/**
 * Shared Google Geocoding transport for the current and legacy APIs.
 *
 * @internal
 */
class GoogleGeocoder {

  /**
   * Return the complete response after validating its first result.
   * Google orders results by relevance; GeoPoint::fromAddress uses the first.
   *
   * @param string $address
   * @param string $apiKey
   * @return \stdClass
   * @throws NoApiKeyException
   * @throws CurlErrorException
   * @throws UnexpectedResponseException
   */
  public static function geocode($address, $apiKey=null) {
    if (!$apiKey) {
      throw new NoApiKeyException();
    }

    list($body, $httpStatus) = self::transport($address, $apiKey);
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

    return $response;
  }

  /**
   * Return the raw decoded body, including API errors and non-object JSON.
   * Legacy callers inspect the response themselves; transport failures are null.
   *
   * @return mixed
   */
  public static function request($address, $apiKey=null) {
    try {
      list($body, $httpStatus) = self::transport($address, $apiKey);
    } catch (\Exception $exception) {
      return null;
    }
    return json_decode($body);
  }

  private static function transport($address, $apiKey) {
    if (!function_exists('curl_init')) {
      throw new UnexpectedResponseException('Google geocoding requires the cURL extension.');
    }
    if (!function_exists('json_decode')) {
      throw new UnexpectedResponseException('Google geocoding requires the JSON extension.');
    }
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' .
      rawurlencode($address) . '&key=' . rawurlencode((string) $apiKey);
    $handle = curl_init();
    if ($handle === false) {
      throw new CurlErrorException('Unable to initialize cURL for Google geocoding.');
    }

    // Close before parsing or throwing response errors. Avoid finally so the
    // runtime remains compatible with PHP 5.4.
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
    } catch (\Exception $exception) {
      self::close($handle);
      throw $exception;
    }
    self::close($handle);

    if ($body === false) {
      throw new CurlErrorException($error !== '' ? $error : 'Google geocoding request failed.');
    }
    return array($body, $httpStatus);
  }

  private static function close(&$handle) {
    // PHP 5/7 use resources. PHP 8 releases CurlHandle objects when their last
    // reference is dropped; curl_close is a no-op and deprecated in PHP 8.5.
    if (is_resource($handle)) {
      curl_close($handle);
    }
    $handle = null;
  }
}
