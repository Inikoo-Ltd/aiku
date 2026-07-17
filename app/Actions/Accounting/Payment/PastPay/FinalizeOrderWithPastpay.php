<?php

/*
 * author Arya Permana - Kirin
 * created on 02-07-2025-17h-39m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\Payment\PastPay;

use App\Actions\Accounting\Traits\CalculatesPaymentWithBalance;
use App\Actions\RetinaAction;
use App\Models\Accounting\Invoice;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FinalizeOrderWithPastpay extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use CalculatesPaymentWithBalance;
    use WithPastpayConfiguration;

    public function handle(Invoice $invoice): array
    {
        $order = $invoice->order;

        return $this->pastpayFinalizeOrder($invoice, [
            'termDays' => Arr::get($order->data, 'pastpay.termDays', 30)
        ]);
    }

    public string $commandSignature = 'test_finalize_pastpay';

    public function asCommand(): int
    {
        $invoice = Invoice::where('slug', 'awp31151')->first();
        $this->handle($invoice);

        return 1;
    }
}
