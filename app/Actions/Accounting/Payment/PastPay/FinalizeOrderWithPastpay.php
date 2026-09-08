<?php

/*
 * author Arya Permana - Kirin
 * created on 02-07-2025-17h-39m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\Payment\PastPay;

use App\Actions\Accounting\Invoice\WithInvoicesExport;
use App\Actions\RetinaAction;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\PaymentAccountShop;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FinalizeOrderWithPastpay extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithPastpayConfiguration;
    use WithInvoicesExport;

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(Invoice $invoice): array
    {
        $order = $invoice->order;

        /** @var PaymentAccountShop $paymentAccountShop */
        $paymentAccountShop = $invoice->shop->paymentAccountShops()
            ->where('type', PaymentAccountTypeEnum::PASTPAY)
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)
            ->first();

        $this->paymentAccount = $paymentAccountShop->paymentAccount;

        return $this->pastpayFinalizeOrder($invoice, [
            'termDays'   => (int) Arr::get($order->data, 'pastpay.termDays', 30),
            'invoicePdf' => 'data:application/pdf;base64,'.base64_encode($this->getInvoicePdfContent($invoice)),
        ]);
    }

    public string $commandSignature = 'pastpay:finalize {invoice}';

    public function asCommand(\Illuminate\Console\Command $command): int
    {
        $invoice = Invoice::where('slug', $command->argument('invoice'))->firstOrFail();
        $result  = $this->handle($invoice);
        $command->info(json_encode($result));

        return 0;
    }
}
