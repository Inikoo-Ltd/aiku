<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Unit;

use App\Actions\Accounting\Invoice\WithInvoicesExport;
use PHPUnit\Framework\TestCase;

class InvoiceOutOfStockLineTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new class () {
            use WithInvoicesExport;
        };
    }

    private function line(?int $transactionId, string $net, string $gross): object
    {
        return (object)[
            'transaction_id' => $transactionId,
            'net_amount'     => $net,
            'gross_amount'   => $gross,
        ];
    }

    public function testItMovesLinesThatAreOutOfStockAndCarryNoMoney(): void
    {
        $this->assertTrue($this->subject->isFullyOutOfStockLine($this->line(10, '0.00', '0.00'), [10]));
    }

    public function testItKeepsLinesThatWereNotFlaggedOutOfStock(): void
    {
        $this->assertFalse($this->subject->isFullyOutOfStockLine($this->line(11, '0.00', '0.00'), [10]));
        $this->assertFalse($this->subject->isFullyOutOfStockLine($this->line(null, '0.00', '0.00'), [10]));
    }

    public function testItNeverMovesAPricedLineOutOfTheTotalsTable(): void
    {
        $this->assertFalse($this->subject->isFullyOutOfStockLine($this->line(10, '4.16', '4.16'), [10]));
        $this->assertFalse($this->subject->isFullyOutOfStockLine($this->line(10, '0.00', '4.16'), [10]));
        $this->assertFalse($this->subject->isFullyOutOfStockLine($this->line(10, '4.16', '0.00'), [10]));
    }

    public function testItReportsTheMissingQuantityOfShortShippedLines(): void
    {
        $this->assertSame(3.5, $this->subject->undeliveredQuantity((object)['quantity' => '8.5', 'transaction' => (object)['quantity_ordered' => '12']]));
        $this->assertSame(12.0, $this->subject->undeliveredQuantity((object)['quantity' => '0', 'transaction' => (object)['quantity_ordered' => '12']]));
        $this->assertSame(0.0, $this->subject->undeliveredQuantity((object)['quantity' => '6', 'transaction' => (object)['quantity_ordered' => '6']]));
        $this->assertSame(0.0, $this->subject->undeliveredQuantity((object)['quantity' => '1', 'transaction' => (object)['quantity_ordered' => '0']]));
        $this->assertSame(0.0, $this->subject->undeliveredQuantity((object)['quantity' => '1', 'transaction' => null]));
    }

    public function testDiscountPercentageLabel(): void
    {
        $this->assertSame('10%', discountPercentageLabel('125.84', '113.26'));
        $this->assertSame('5%', discountPercentageLabel('49.56', '47.08'));
        $this->assertSame('12.5%', discountPercentageLabel('100.00', '87.50'));
        $this->assertSame('15%', discountPercentageLabel('6.64', '5.64'));
        $this->assertSame('10%', discountPercentageLabel('5.86', '5.27'));
        $this->assertSame('100%', discountPercentageLabel('15.00', '0.00'));
        $this->assertNull(discountPercentageLabel('20.00', '20.00'));
        $this->assertNull(discountPercentageLabel('0.00', '0.00'));
        $this->assertNull(discountPercentageLabel(null, '5.00'));
        $this->assertNull(discountPercentageLabel('10.00', '12.00'));
    }
}
