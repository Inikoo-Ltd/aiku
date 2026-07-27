<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jul 2026 10:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Unit;

use App\Http\Resources\Traits\HasPriceMetrics;
use PHPUnit\Framework\TestCase;

class HasPriceMetricsTest extends TestCase
{
    /**
     * @return array{0:mixed,1:float,2:float,3:float,4:int,5:float}
     */
    private function metrics(float|int|null $rrp, float|int $price, float|int $units): array
    {
        return (new class () {
            use HasPriceMetrics {
                getPriceMetrics as public;
            }
        })->getPriceMetrics($rrp, $price, $units);
    }

    public function testPerOuterAndPerUnitAreConsistent(): void
    {
        [$margin, $rrpPerUnit, $profit, $profitPerUnit, $units, $pricePerUnit] = $this->metrics(74.88, 21.50, 16);

        $this->assertEquals(16, $units);
        $this->assertEquals(4.68, $rrpPerUnit);
        $this->assertEquals(1.34, $pricePerUnit);
        $this->assertEquals(53.38, $profit);
        $this->assertEquals(3.34, $profitPerUnit);
        $this->assertEquals('71.3%', $margin);
        $this->assertEqualsWithDelta($profit, $profitPerUnit * $units, 0.5);
    }

    public function testDiscountedProfitPerUnitMatchesPerOuter(): void
    {
        $discountFactor = 1 - 0.25;

        [$margin, , $profit, $profitPerUnit] = $this->metrics(74.88, $discountFactor * 21.50, 16);

        $this->assertEquals(58.75, $profit);
        $this->assertEquals(3.67, $profitPerUnit);
        $this->assertEquals('78.5%', $margin);
    }

    public function testZeroUnitsDoesNotDivideByZero(): void
    {
        [, $rrpPerUnit, $profit, $profitPerUnit, $units, $pricePerUnit] = $this->metrics(74.88, 21.50, 0);

        $this->assertEquals(0, $units);
        $this->assertEquals(74.88, $rrpPerUnit);
        $this->assertEquals(53.38, $profit);
        $this->assertEquals(53.38, $profitPerUnit);
        $this->assertEquals(21.50, $pricePerUnit);
    }

    public function testNoRrpYieldsNoProfit(): void
    {
        [$margin, $rrpPerUnit, $profit, $profitPerUnit, , $pricePerUnit] = $this->metrics(null, 21.50, 16);

        $this->assertEquals(0, $margin);
        $this->assertEquals(0, $rrpPerUnit);
        $this->assertEquals(0, $profit);
        $this->assertEquals(0, $profitPerUnit);
        $this->assertEquals(1.34, $pricePerUnit);
    }
}
