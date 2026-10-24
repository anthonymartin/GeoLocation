<?php

use AnthonyMartin\GeoLocation\Earth;
use AnthonyMartin\GeoLocation\Exceptions;
use PHPUnit\Framework\TestCase;

class PackagingRegressionTest extends TestCase {
  public function testUnitsAreCaseInsensitiveAndStrict() {
    foreach (array('mi', 'miles', 'MI', 'Miles') as $unit) {
      $this->assertSame(Earth::RADIUS_MI, Earth::getRadius($unit));
    }
    foreach (array('km', 'kilometers', 'KM', 'Kilometers') as $unit) {
      $this->assertSame(Earth::RADIUS_KM, Earth::getRadius($unit));
    }
    foreach (array(true, false, null, 0, 1, array(), new stdClass(), 'feet', '') as $unit) {
      try {
        Earth::getRadius($unit);
        $this->fail('Invalid units must throw.');
      } catch (\InvalidArgumentException $exception) {
        $this->assertInstanceOf(Exceptions\ExceptionInterface::class, $exception);
        $this->assertNotSame('', $exception->getMessage());
      }
    }
  }

  public function testLibraryExceptionsHaveACommonCatchType() {
    foreach (array('Exception', 'InvalidArgumentException', 'CurlErrorException',
      'InvalidBoundingBoxCoordinatesException', 'NoApiKeyException',
      'OutOfBoundsException', 'UnexpectedResponseException') as $name) {
      $class = 'AnthonyMartin\\GeoLocation\\Exceptions\\' . $name;
      $exception = new $class('test');
      $this->assertInstanceOf(Exceptions\Exception::class, $exception);
      $this->assertInstanceOf(Exceptions\ExceptionInterface::class, $exception);
    }
    $this->assertInstanceOf(Exceptions\ExceptionInterface::class, new Exceptions\InvalidUnitException());
    $this->assertInstanceOf(\InvalidArgumentException::class, new Exceptions\InvalidUnitException());
  }

  public function testClassesImplementLoadableInterfaces() {
    foreach (array('GeoPoint' => 'GeoPointInterface', 'BoundingBox' => 'BoundingBoxInterface',
      'Polygon' => 'Polygon', 'GeoLocation' => 'GeoLocationInterface') as $class => $interface) {
      $this->assertTrue(is_subclass_of('AnthonyMartin\\GeoLocation\\' . $class,
        'AnthonyMartin\\GeoLocation\\Interfaces\\' . $interface));
    }
  }
}
