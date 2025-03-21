<?php

/*
 * author Arya Permana - Kirin
 * created on 18-03-2025-15h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\Invoice;

use App\Actions\OrgAction;
use App\Models\Accounting\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class RefundToCredit extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Invoice $invoice, array $modelData): Invoice
    {

        $paymentAccount = $invoice->shop->paymentAccountShops->first()->paymentAccount;

        return RefundToInvoice::make()->action($invoice, $paymentAccount, [
            'amount' => Arr::get($modelData, 'amount'),
            'type' => 'credit',
        ]);
    }

    public function rules(): array
    {
        return [
            'amount'     => ['required', 'numeric'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Invoice $refund, array $modelData): Invoice
    {
        $this->initialisationFromShop($refund->shop, $modelData);

        return $this->handle($refund, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Invoice $refund, ActionRequest $request): void
    {
        $this->initialisationFromShop($refund->shop, $request);

        $this->handle($refund, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
