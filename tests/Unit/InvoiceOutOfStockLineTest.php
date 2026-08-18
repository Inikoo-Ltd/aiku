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
}
