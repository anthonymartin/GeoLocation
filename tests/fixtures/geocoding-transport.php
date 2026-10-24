<?php

namespace AnthonyMartin\GeoLocation {
  // Namespace-level functions exercise the actual transport without a network
  // request or a Google API key. Every request is an explicit local fixture.
  class GeocodingCurlFixture {
    public static $body;
    public static $httpStatus;
    public static $error;
    public static $options;
    public static $events;
    public static $initializes;
    public static $closes;
    public static $initializeFails;
    public static $optionFails;
    public static $executeThrows;
    public static $executeError;
    public static $objectHandle;

    public static function reset() {
      self::$body = '{"status":"OK","results":[{"geometry":{"location":{"lat":40.5,"lng":-74.4}}}]}';
      self::$httpStatus = 200;
      self::$error = '';
      self::$options = array();
      self::$events = array();
      self::$initializes = 0;
      self::$closes = 0;
      self::$initializeFails = false;
      self::$optionFails = false;
      self::$executeThrows = false;
      self::$executeError = false;
      self::$objectHandle = true;
    }
  }

  class GeocodingObjectHandle {
    public function __destruct() {
      GeocodingCurlFixture::$events[] = 'destroy';
      GeocodingCurlFixture::$closes++;
    }
  }

  function curl_init() {
    GeocodingCurlFixture::$initializes++;
    if (GeocodingCurlFixture::$initializeFails) {
      return false;
    }
    return GeocodingCurlFixture::$objectHandle ? new GeocodingObjectHandle() : fopen('php://temp', 'r+');
  }

  function curl_setopt($handle, $option, $value) {
    GeocodingCurlFixture::$options[$option] = $value;
    return !GeocodingCurlFixture::$optionFails;
  }

  function curl_exec($handle) {
    GeocodingCurlFixture::$events[] = 'execute';
    if (GeocodingCurlFixture::$executeError) {
      throw new \Error('Transport engine error');
    }
    if (GeocodingCurlFixture::$executeThrows) {
      throw new \RuntimeException('Transport exception');
    }
    return GeocodingCurlFixture::$body;
  }

  function curl_error($handle) {
    GeocodingCurlFixture::$events[] = 'error';
    return GeocodingCurlFixture::$error;
  }

  function curl_getinfo($handle, $option) {
    return GeocodingCurlFixture::$httpStatus;
  }

  function curl_close($handle) {
    GeocodingCurlFixture::$events[] = 'close';
    GeocodingCurlFixture::$closes++;
    fclose($handle);
  }
}

