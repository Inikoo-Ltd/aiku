<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Aug 2025 18:49:28 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ConvertUnitsTest extends TestCase
{
    /**
     * Test volume conversions
     */
    public function testVolumeConversions()
    {
        // m3 to other units
        $this->assertEquals(1000, convertUnits(1, 'm3', 'l'));
        $this->assertEquals(1000000, convertUnits(1, 'm3', 'ml'));

        // l to other units
        $this->assertEquals(0.001, convertUnits(1, 'l', 'm3'));
        $this->assertEquals(1000, convertUnits(1, 'l', 'ml'));

        // ml to other units
        $this->assertEquals(0.001, convertUnits(1, 'ml', 'l'));
        $this->assertEquals(0.000001, convertUnits(1, 'ml', 'm3'));
    }

    /**
     * Test weight conversions
     */
    public function testWeightConversions()
    {
        // Kg to other units
        $this->assertEquals(1000, convertUnits(1, 'Kg', 'g'));
        $this->assertEquals(2.20462262, convertUnits(1, 'Kg', 'lb'));
        $this->assertEquals(35.274, convertUnits(1, 'Kg', 'oz'));

        // g to other units
        $this->assertEquals(0.001, convertUnits(1, 'g', 'Kg'));
        $this->assertEquals(0.00220462262, convertUnits(1, 'g', 'lb'));
        $this->assertEquals(0.035274, convertUnits(1, 'g', 'oz'));

        // lb to other units
        $this->assertEquals(0.45359237, convertUnits(1, 'lb', 'Kg'));
        $this->assertEquals(453.59237, convertUnits(1, 'lb', 'g'));
        $this->assertEquals(16, convertUnits(1, 'lb', 'oz'));

        // oz to other units
        $this->assertEquals(0.0283495, convertUnits(1, 'oz', 'Kg'));
        $this->assertEquals(28.3495, convertUnits(1, 'oz', 'g'));
        $this->assertEquals(0.0625, convertUnits(1, 'oz', 'lb'));
    }

    /**
     * Test length conversions
     */
    public function testLengthConversions()
    {
        // m to other units
        $this->assertEquals(1000, convertUnits(1, 'm', 'mm'));
        $this->assertEquals(100, convertUnits(1, 'm', 'cm'));
        $this->assertEquals(1.09361, convertUnits(1, 'm', 'yd'));
        $this->assertEquals(39.3701, convertUnits(1, 'm', 'in'));
        $this->assertEquals(3.28084, convertUnits(1, 'm', 'ft'));

        // mm to other units
        $this->assertEquals(0.001, convertUnits(1, 'mm', 'm'));
        $this->assertEquals(0.1, convertUnits(1, 'mm', 'cm'));

        // cm to other units
        $this->assertEquals(10, convertUnits(1, 'cm', 'mm'));
        $this->assertEquals(0.01, convertUnits(1, 'cm', 'm'));

        // yd to other units
        $this->assertEquals(0.9144, convertUnits(1, 'yd', 'm'));
        $this->assertEquals(36, convertUnits(1, 'yd', 'in'));

        // in to other units
        $this->assertEquals(2.54, convertUnits(1, 'in', 'cm'));
        $this->assertEquals(0.0254, convertUnits(1, 'in', 'm'));

        // ft to other units
        $this->assertEquals(0.3048, convertUnits(1, 'ft', 'm'));
        $this->assertEquals(12, convertUnits(1, 'ft', 'in'));
    }

    /**
     * Test edge cases
     */
    public function testEdgeCases()
    {
        // Same units should return the original value
        $this->assertEquals(5, convertUnits(5, 'm', 'm'));
        $this->assertEquals(10, convertUnits(10, 'Kg', 'Kg'));

        // Unsupported units should return the original value
        $this->assertEquals(7, convertUnits(7, 'unknown', 'm'));
        $this->assertEquals(8, convertUnits(8, 'm', 'unknown'));

        // Zero values
        $this->assertEquals(0, convertUnits(0, 'm', 'cm'));

        // Negative values
        $this->assertEquals(-100, convertUnits(-1, 'm', 'cm'));

        // Decimal values
        $this->assertEquals(25.4, convertUnits(1, 'in', 'mm'));
        $this->assertEquals(50.8, convertUnits(2, 'in', 'mm'));
        $this->assertEquals(2.54, convertUnits(0.1, 'in', 'mm'));
    }
}
