<?php

declare(strict_types=1);

use AnthonyMartin\GeoLocation\Earth;
use PHPUnit\Framework\TestCase;

final class EarthTypesTest extends TestCase {
    public function testConstantsAndBoundsHaveFloatTypes(): void {
        foreach (['MIN_LAT' => 'getMINLAT', 'MAX_LAT' => 'getMAXLAT', 'MIN_LON' => 'getMINLON', 'MAX_LON' => 'getMAXLON'] as $property => $getter) {
            self::assertSame('float', (string) (new ReflectionProperty(Earth::class, $property))->getType());
            self::assertSame(Earth::${$property}, Earth::$getter());
        }
        foreach (['RADIUS_KM' => 6371.01, 'RADIUS_MI' => 3958.762079, 'WGS84_A' => 6378137.0, 'WGS84_F' => 1 / 298.257223563] as $name => $value) {
            $constant = new ReflectionClassConstant(Earth::class, $name);
            self::assertSame('float', (string) $constant->getType());
            self::assertSame($value, $constant->getValue());
        }
    }
}
