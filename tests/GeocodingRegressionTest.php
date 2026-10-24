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
      self::$objectHandle = false;
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

namespace {
  require_once __DIR__ . '/../vendor/autoload.php';

  use AnthonyMartin\GeoLocation\GeoPoint;
  use AnthonyMartin\GeoLocation\GeoLocation;
  use AnthonyMartin\GeoLocation\GeocodingCurlFixture;
  use AnthonyMartin\GeoLocation\Exceptions\CurlErrorException;
  use AnthonyMartin\GeoLocation\Exceptions\NoApiKeyException;
  use AnthonyMartin\GeoLocation\Exceptions\UnexpectedResponseException;
  use PHPUnit\Framework\TestCase;

  class GeocodingRegressionTest extends TestCase {
    protected function setUp(): void {
      GeocodingCurlFixture::reset();
    }

    public function testAddressAndKeyAreEncodedAndTimeoutsAreBounded() {
      $address = '123 Main St & Suite #4, Montréal';
      $key = 'key&reserved=/?+#';
      $point = GeoPoint::fromAddress($address, $key);
      $this->assertSame(40.5, $point->getLatitude());
      $this->assertSame(-74.4, $point->getLongitude());
      $url = GeocodingCurlFixture::$options[CURLOPT_URL];
      $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));
      $this->assertSame('maps.googleapis.com', parse_url($url, PHP_URL_HOST));
      parse_str(parse_url($url, PHP_URL_QUERY), $query);
      $this->assertSame(array('address' => $address, 'key' => $key), $query);
      $this->assertTrue(GeocodingCurlFixture::$options[CURLOPT_RETURNTRANSFER]);
      $this->assertSame(5, GeocodingCurlFixture::$options[CURLOPT_CONNECTTIMEOUT]);
      $this->assertSame(10, GeocodingCurlFixture::$options[CURLOPT_TIMEOUT]);
      $this->assertSame(1, GeocodingCurlFixture::$closes);
    }

    public function testFirstOfMultipleResultsIsChosen() {
      GeocodingCurlFixture::$body = '{"status":"OK","results":[' .
        '{"geometry":{"location":{"lat":0,"lng":0}}},' .
        '{"geometry":{"location":{"lat":12,"lng":34}}}]}';
      $point = GeoPoint::fromAddress('ambiguous address', 'key');
      $this->assertEquals(0, $point->getLatitude());
      $this->assertEquals(0, $point->getLongitude());
      $this->assertSame(1, GeocodingCurlFixture::$closes);
    }

    public function testLegacyGeocoderPreservesTheCompleteDecodedResponse() {
      GeocodingCurlFixture::$body = '{"status":"OK","extra":"kept","results":[' .
        '{"formatted_address":"First","geometry":{"location":{"lat":0,"lng":0}}},' .
        '{"formatted_address":"Second","geometry":{"location":{"lat":12,"lng":34}}}]}';
      $response = GeoLocation::getGeocodeFromGoogle('ambiguous address', 'key');
      $this->assertEquals(json_decode(GeocodingCurlFixture::$body), $response);
      $this->assertCount(2, $response->results);
      $this->assertSame(1, GeocodingCurlFixture::$closes);
    }

    public function testMissingKeyFailsBeforeOpeningConnection() {
      foreach (array(null, '') as $key) {
        $this->captureException(function () use ($key) {
          GeoPoint::fromAddress('address', $key);
        }, NoApiKeyException::class);
      }
      $this->assertSame(0, GeocodingCurlFixture::$initializes);
      $this->assertSame(0, GeocodingCurlFixture::$closes);
    }

    public function testLegacyGeocoderReturnsApiErrorsWithoutValidation() {
      foreach (array('ZERO_RESULTS', 'REQUEST_DENIED', 'OVER_QUERY_LIMIT') as $status) {
        foreach (array(null, 'key') as $key) {
          GeocodingCurlFixture::reset();
          GeocodingCurlFixture::$body = json_encode(array('status' => $status, 'error_message' => 'Details', 'results' => array()));
          $this->assertEquals(json_decode(GeocodingCurlFixture::$body), GeoLocation::getGeocodeFromGoogle('address', $key));
          $this->assertSame(1, GeocodingCurlFixture::$closes);
          $this->assertSame(5, GeocodingCurlFixture::$options[CURLOPT_CONNECTTIMEOUT]);
          $this->assertSame(10, GeocodingCurlFixture::$options[CURLOPT_TIMEOUT]);
        }
      }
    }

    public function testLegacyGeocoderReturnsNullOnTransportOrJsonFailure() {
      foreach (array(false, '', '{', '<html>Error</html>') as $body) {
        GeocodingCurlFixture::reset();
        GeocodingCurlFixture::$body = $body;
        $this->assertNull(GeoLocation::getGeocodeFromGoogle('address'));
        $this->assertSame(1, GeocodingCurlFixture::$closes);
      }
      foreach (array('initializeFails', 'optionFails', 'executeThrows') as $failure) {
        GeocodingCurlFixture::reset();
        GeocodingCurlFixture::$$failure = true;
        $this->assertNull(GeoLocation::getGeocodeFromGoogle('address'));
        $this->assertSame($failure === 'initializeFails' ? 0 : 1, GeocodingCurlFixture::$closes);
      }
    }

    public function testLegacyGeocoderPreservesDecodedScalarsAndHttpErrorBodies() {
      foreach (array('null', 'false', '42', '"text"', '[]', '{}', '{"status":"REQUEST_DENIED"}') as $body) {
        GeocodingCurlFixture::reset();
        GeocodingCurlFixture::$body = $body;
        GeocodingCurlFixture::$httpStatus = 403;
        $this->assertEquals(json_decode($body), GeoLocation::getGeocodeFromGoogle('address', 'key'));
        $this->assertSame(1, GeocodingCurlFixture::$closes);
      }
    }

    public function testCurlFailurePreservesErrorAfterExecutionAndClosesHandle() {
      GeocodingCurlFixture::$body = false;
      GeocodingCurlFixture::$error = 'Operation timed out after 10000 milliseconds';
      $exception = $this->captureException(function () {
        GeoPoint::fromAddress('address', 'key');
      }, CurlErrorException::class);
      $this->assertSame(GeocodingCurlFixture::$error, $exception->getMessage());
      $this->assertInstanceOf(UnexpectedResponseException::class, $exception);
      $this->assertInstanceOf(\AnthonyMartin\GeoLocation\Exceptions\ExceptionInterface::class, $exception);
      $this->assertSame(array('execute', 'error', 'close'), GeocodingCurlFixture::$events);
      $this->assertSame(1, GeocodingCurlFixture::$closes);
    }

    public function testInitializationFailureHasAClearError() {
      GeocodingCurlFixture::$initializeFails = true;
      $exception = $this->captureException(function () {
        GeoPoint::fromAddress('address', 'key');
      }, CurlErrorException::class);
      $this->assertStringContainsString('initialize cURL', $exception->getMessage());
      $this->assertSame(0, GeocodingCurlFixture::$closes);
    }

    public function testOptionFailureClosesHandle() {
      GeocodingCurlFixture::$optionFails = true;
      $this->captureException(function () {
        GeoPoint::fromAddress('address', 'key');
      }, CurlErrorException::class);
      $this->assertSame(1, GeocodingCurlFixture::$closes);
    }

    public function testTransportExceptionClosesHandle() {
      GeocodingCurlFixture::$executeThrows = true;
      $this->captureException(function () {
        GeoPoint::fromAddress('address', 'key');
      }, RuntimeException::class);
      $this->assertSame(1, GeocodingCurlFixture::$closes);
    }

    public function testObjectHandleIsReleasedWithoutDeprecatedCurlClose() {
      GeocodingCurlFixture::$objectHandle = true;
      GeoPoint::fromAddress('address', 'key');
      $this->assertSame(array('execute', 'destroy'), GeocodingCurlFixture::$events);
      $this->assertSame(1, GeocodingCurlFixture::$closes);
    }

    public function testObjectHandleIsReleasedWhenExecutionThrows() {
      GeocodingCurlFixture::$objectHandle = true;
      GeocodingCurlFixture::$executeThrows = true;
      $this->captureException(function () {
        GeoPoint::fromAddress('address', 'key');
      }, RuntimeException::class);
      $this->assertSame(array('execute', 'destroy'), GeocodingCurlFixture::$events);
      $this->assertSame(1, GeocodingCurlFixture::$closes);
    }

    public function testHttpErrorsAreReportedBeforeParsingBody() {
      foreach (array(0, 302, 403, 429, 500) as $status) {
        GeocodingCurlFixture::reset();
        GeocodingCurlFixture::$httpStatus = $status;
        GeocodingCurlFixture::$body = '<html>HTTP error</html>';
        $exception = $this->captureException(function () {
          GeoPoint::fromAddress('address', 'key');
        }, UnexpectedResponseException::class);
        $this->assertStringContainsString('HTTP status ' . $status, $exception->getMessage());
        $this->assertSame(1, GeocodingCurlFixture::$closes);
      }
    }

    public function testInvalidJsonBodiesAreRejectedAfterClosingHandle() {
      foreach (array('', '<html>Error</html>', '{', 'null', '[]', 'true', '"text"') as $body) {
        GeocodingCurlFixture::reset();
        GeocodingCurlFixture::$body = $body;
        $exception = $this->captureException(function () {
          GeoPoint::fromAddress('address', 'key');
        }, UnexpectedResponseException::class);
        $this->assertStringContainsString('invalid JSON', $exception->getMessage());
        $this->assertSame(1, GeocodingCurlFixture::$closes);
      }
    }

    public function testZeroResultsHasAClearError() {
      GeocodingCurlFixture::$body = '{"status":"ZERO_RESULTS","results":[]}';
      $exception = $this->captureException(function () {
        GeoPoint::fromAddress('address', 'key');
      }, UnexpectedResponseException::class);
      $this->assertStringContainsString('no results (ZERO_RESULTS)', $exception->getMessage());
      $this->assertSame(1, GeocodingCurlFixture::$closes);
    }

    public function testApiFailuresPreserveStatusAndGoogleErrorMessage() {
      foreach (array('REQUEST_DENIED', 'OVER_QUERY_LIMIT', 'INVALID_REQUEST', 'UNKNOWN_ERROR') as $status) {
        GeocodingCurlFixture::reset();
        GeocodingCurlFixture::$body = json_encode(array('status' => $status, 'error_message' => 'Details from Google'));
        $exception = $this->captureException(function () {
          GeoPoint::fromAddress('address', 'key');
        }, UnexpectedResponseException::class);
        $this->assertStringContainsString($status, $exception->getMessage());
        $this->assertStringContainsString('Details from Google', $exception->getMessage());
        $this->assertSame(1, GeocodingCurlFixture::$closes);
      }
    }

    public function testMissingStatusAndMalformedResultsAreRejected() {
      $bodies = array(
        '{}', '{"status":true}', '{"status":"OK"}',
        '{"status":"OK","results":{}}', '{"status":"OK","results":[]}',
        '{"status":"OK","results":[{}]}',
        '{"status":"OK","results":[{"geometry":{"location":{"lat":1}}}]}',
        '{"status":"OK","results":[{"geometry":{"location":{"lat":null,"lng":1}}}]}',
        '{"status":"OK","results":[{"geometry":{"location":{"lat":false,"lng":1}}}]}',
        '{"status":"OK","results":[{"geometry":{"location":{"lat":"bad","lng":1}}}]}',
        '{"status":"OK","results":[{"geometry":{"location":{"lat":91,"lng":1}}}]}',
        '{"status":"OK","results":[{"geometry":{"location":{"lat":1,"lng":181}}}]}',
        '{"status":"OK","results":[{"geometry":{"location":{"lat":1e999,"lng":1}}}]}'
      );
      foreach ($bodies as $body) {
        GeocodingCurlFixture::reset();
        GeocodingCurlFixture::$body = $body;
        $this->captureException(function () {
          GeoPoint::fromAddress('address', 'key');
        }, UnexpectedResponseException::class);
        $this->assertSame(1, GeocodingCurlFixture::$closes);
      }
    }

    private function captureException($operation, $expectedClass) {
      try {
        $operation();
      } catch (Exception $exception) {
        $this->assertInstanceOf($expectedClass, $exception);
        return $exception;
      }
      $this->fail('Expected ' . $expectedClass . ' to be thrown.');
    }
  }
}
