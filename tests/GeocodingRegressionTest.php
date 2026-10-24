<?php

namespace {
  require_once __DIR__ . '/../vendor/autoload.php';
  require_once __DIR__ . '/fixtures/native-curl-transport.php';
  require_once __DIR__ . '/fixtures/psr-geocoding.php';

  use AnthonyMartin\GeoLocation\GeoPoint;
  use AnthonyMartin\GeoLocation\GoogleGeocoder;
  use AnthonyMartin\GeoLocation\Geocoding\GeocodingCurlFixture;
  use AnthonyMartin\GeoLocation\Exceptions\CurlErrorException;
  use AnthonyMartin\GeoLocation\Exceptions\NoApiKeyException;
  use AnthonyMartin\GeoLocation\Exceptions\UnexpectedResponseException;
  use PHPUnit\Framework\TestCase;
  use GeoLocationTests\FixtureHttpClient;
  use Nyholm\Psr7\Factory\Psr17Factory;

  class GeocodingRegressionTest extends TestCase {
    private FixtureHttpClient $client;
    protected function setUp(): void {
      GeocodingCurlFixture::reset();
      $this->client=new FixtureHttpClient(GeocodingCurlFixture::$body);
    }

    public function testAddressAndKeyAreEncodedAndTimeoutsAreBounded() {
      $address = '123 Main St & Suite #4, Montréal';
      $key = 'key&reserved=/?+#';
      $point = (new GoogleGeocoder($key))->geocode($address);
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
      $this->client->body = '{"status":"OK","results":[' .
        '{"geometry":{"location":{"lat":0,"lng":0}}},' .
        '{"geometry":{"location":{"lat":12,"lng":34}}}]}';
      $point = (new GoogleGeocoder('key', $this->client, new Psr17Factory()))->geocode('ambiguous address');
      $this->assertEquals(0, $point->getLatitude());
      $this->assertEquals(0, $point->getLongitude());

    }

    public function testPartialMatchesReturnTheFirstValidatedPoint() {
      $this->client->body = '{"status":"OK","results":[' .
        '{"partial_match":true,"geometry":{"location":{"lat":"40.5","lng":"-74.4"}}}]}';
      $point = (new GoogleGeocoder('key', $this->client, new Psr17Factory()))->geocode('partial address');
      $this->assertSame(40.5, $point->getLatitude());
      $this->assertSame(-74.4, $point->getLongitude());
    }

    public function testFinallyReleasesTheHandleForEngineErrorsToo() {
      GeocodingCurlFixture::$executeError = true;
      try {
        (new GoogleGeocoder('key'))->geocode('address');
        $this->fail('Expected a transport engine error.');
      } catch (\Error $error) {
        $this->assertSame('Transport engine error', $error->getMessage());
      }
      // An Error trace can retain function arguments until the Error is released.
      unset($error);
      $this->assertSame(array('execute', 'destroy'), GeocodingCurlFixture::$events);
      $this->assertSame(1, GeocodingCurlFixture::$closes);
    }

    public function testMissingKeyFailsBeforeOpeningConnection() {
      foreach (array('') as $key) {
        $this->captureException(function () use ($key) {
          (new GoogleGeocoder($key))->geocode('address');
        }, NoApiKeyException::class);
      }
      $this->assertSame(0, GeocodingCurlFixture::$initializes);
      $this->assertSame(0, GeocodingCurlFixture::$closes);
    }

    public function testCurlFailurePreservesErrorAfterExecutionAndClosesHandle() {
      GeocodingCurlFixture::$body = false;
      GeocodingCurlFixture::$error = 'Operation timed out after 10000 milliseconds';
      $exception = $this->captureException(function () {
        (new GoogleGeocoder('key'))->geocode('address');
      }, CurlErrorException::class);
      $this->assertSame(GeocodingCurlFixture::$error, $exception->getMessage());
      $this->assertInstanceOf(UnexpectedResponseException::class, $exception);
      $this->assertInstanceOf(\AnthonyMartin\GeoLocation\Exceptions\ExceptionInterface::class, $exception);
      $this->assertSame(array('execute', 'error', 'destroy'), GeocodingCurlFixture::$events);
      $this->assertSame(1, GeocodingCurlFixture::$closes);
    }

    public function testInitializationFailureHasAClearError() {
      GeocodingCurlFixture::$initializeFails = true;
      $exception = $this->captureException(function () {
        (new GoogleGeocoder('key'))->geocode('address');
      }, CurlErrorException::class);
      $this->assertStringContainsString('initialize cURL', $exception->getMessage());
      $this->assertSame(0, GeocodingCurlFixture::$closes);
    }

    public function testOptionFailureClosesHandle() {
      GeocodingCurlFixture::$optionFails = true;
      $this->captureException(function () {
        (new GoogleGeocoder('key'))->geocode('address');
      }, CurlErrorException::class);
      $this->assertSame(1, GeocodingCurlFixture::$closes);
    }

    public function testTransportExceptionClosesHandle() {
      GeocodingCurlFixture::$executeThrows = true;
      $this->captureException(function () {
        (new GoogleGeocoder('key'))->geocode('address');
      }, RuntimeException::class);
      $this->assertSame(1, GeocodingCurlFixture::$closes);
    }

    public function testObjectHandleIsReleasedWithoutDeprecatedCurlClose() {
      GeocodingCurlFixture::$objectHandle = true;
      (new GoogleGeocoder('key'))->geocode('address');
      $this->assertSame(array('execute', 'destroy'), GeocodingCurlFixture::$events);
      $this->assertSame(1, GeocodingCurlFixture::$closes);
    }

    public function testObjectHandleIsReleasedWhenExecutionThrows() {
      GeocodingCurlFixture::$objectHandle = true;
      GeocodingCurlFixture::$executeThrows = true;
      $this->captureException(function () {
        (new GoogleGeocoder('key'))->geocode('address');
      }, RuntimeException::class);
      $this->assertSame(array('execute', 'destroy'), GeocodingCurlFixture::$events);
      $this->assertSame(1, GeocodingCurlFixture::$closes);
    }

    public function testHttpErrorsAreReportedBeforeParsingBody() {
      foreach (array(0, 302, 403, 429, 500) as $status) {
        $this->client=new FixtureHttpClient();
        $this->client->status = $status;
        $this->client->body = '<html>HTTP error</html>';
        $exception = $this->captureException(function () {
          (new GoogleGeocoder('key', $this->client, new Psr17Factory()))->geocode('address');
        }, UnexpectedResponseException::class);
        $this->assertStringContainsString('HTTP status ' . $status, $exception->getMessage());

      }
    }

    public function testInvalidJsonBodiesAreRejectedThroughPsrClient() {
      foreach (array('', '<html>Error</html>', '{', 'null', '[]', 'true', '"text"') as $body) {
        $this->client=new FixtureHttpClient();
        $this->client->body = $body;
        $exception = $this->captureException(function () {
          (new GoogleGeocoder('key', $this->client, new Psr17Factory()))->geocode('address');
        }, UnexpectedResponseException::class);
        $this->assertStringContainsString('invalid JSON', $exception->getMessage());

      }
    }

    public function testZeroResultsHasAClearError() {
      $this->client->body = '{"status":"ZERO_RESULTS","results":[]}';
      $exception = $this->captureException(function () {
        (new GoogleGeocoder('key', $this->client, new Psr17Factory()))->geocode('address');
      }, UnexpectedResponseException::class);
      $this->assertStringContainsString('no results (ZERO_RESULTS)', $exception->getMessage());

    }

    public function testApiFailuresPreserveStatusAndGoogleErrorMessage() {
      foreach (array('REQUEST_DENIED', 'OVER_QUERY_LIMIT', 'INVALID_REQUEST', 'UNKNOWN_ERROR') as $status) {
        $this->client=new FixtureHttpClient();
        $this->client->body = json_encode(array('status' => $status, 'error_message' => 'Details from Google'));
        $exception = $this->captureException(function () {
          (new GoogleGeocoder('key', $this->client, new Psr17Factory()))->geocode('address');
        }, UnexpectedResponseException::class);
        $this->assertStringContainsString($status, $exception->getMessage());
        $this->assertStringContainsString('Details from Google', $exception->getMessage());

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
        $this->client=new FixtureHttpClient();
        $this->client->body = $body;
        $this->captureException(function () {
          (new GoogleGeocoder('key', $this->client, new Psr17Factory()))->geocode('address');
        }, UnexpectedResponseException::class);

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
