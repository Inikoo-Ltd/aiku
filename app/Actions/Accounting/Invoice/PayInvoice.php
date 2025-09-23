<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:11:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\OrgAction;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class PayInvoice extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Invoice $invoice, PaymentAccount $paymentAccount, array $modelData): Payment
    {

        $payment = StorePayment::make()->action($invoice->customer, $paymentAccount, $modelData);

        if ($paymentAccount->is_accounts) {
            $creditTransactionData = [
                'amount'     => -$payment->amount,
                'type'       => CreditTransactionTypeEnum::PAYMENT,
                'payment_id' => $payment->id,
            ];
            StoreCreditTransaction::make()->action($invoice->customer, $creditTransactionData);
        }

        AttachPaymentToInvoice::make()->action($invoice, $payment, []);
        if ($invoice->order) {
            AttachPaymentToOrder::make()->action($invoice->order, $payment, []);
        }


        return $payment;
    }

    public function rules(): array
    {
        return [
            'amount'    => ['required', 'decimal:0,2'],
            'reference' => ['nullable', 'string', 'max:255'],
            'status'    => ['sometimes', 'required', Rule::enum(PaymentStatusEnum::class)],
            'state'     => ['sometimes', 'required', Rule::enum(PaymentStateEnum::class)],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Invoice $invoice, PaymentAccount $paymentAccount, array $modelData): Payment
    {
        $this->initialisationFromShop($invoice->shop, $modelData);

        return $this->handle($invoice, $paymentAccount, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Invoice $invoice, PaymentAccount $paymentAccount, ActionRequest $request): void
    {
        $this->initialisationFromShop($invoice->shop, $request);

        $this->handle($invoice, $paymentAccount, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
