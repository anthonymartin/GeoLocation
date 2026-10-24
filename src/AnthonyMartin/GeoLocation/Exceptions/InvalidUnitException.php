<?php

namespace AnthonyMartin\GeoLocation\Exceptions;

/** Retains compatibility with callers catching PHP's InvalidArgumentException. */
class InvalidUnitException extends \InvalidArgumentException implements ExceptionInterface {
}
