<?php

/*
 * author Arya Permana - Kirin
 * created on 18-03-2025-15h-13m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\OrgAction;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class RefundToPaymentAccount extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Invoice $refund, PaymentAccount $paymentAccount, array $modelData): Payment
    {
        $payment = StorePayment::make()->action($refund->customer, $paymentAccount, [
            'amount' => -abs(Arr::get($modelData, 'amount')),
            'status' => PaymentStatusEnum::SUCCESS->value,
            'state' => PaymentStateEnum::COMPLETED->value,
        ]);

        AttachPaymentToInvoice::make()->action($refund, $payment, []);

        return $payment;
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
    public function action(Invoice $refund, PaymentAccount $paymentAccount, array $modelData): Payment
    {
        $this->initialisationFromShop($refund->shop, $modelData);

        return $this->handle($refund, $paymentAccount, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Invoice $refund, PaymentAccount $paymentAccount, ActionRequest $request): void
    {
        $this->initialisationFromShop($refund->shop, $request);

        $this->handle($refund, $paymentAccount, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
