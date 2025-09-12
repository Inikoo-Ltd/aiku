<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:11:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Invoice\AttachPaymentToInvoice;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\OrgAction;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\Ordering\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class PayOrder extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Order $order, PaymentAccount $paymentAccount, array $modelData): Payment
    {

        $payment = StorePayment::make()->action($order->customer, $paymentAccount, $modelData);


        if($paymentAccount->is_accounts) {
            $creditTransactionData = [
                'amount'     => -$payment->amount,
                'type'       => CreditTransactionTypeEnum::PAYMENT,
                'payment_id' => $payment->id,
            ];
            StoreCreditTransaction::make()->action($order->customer, $creditTransactionData);

        }


        AttachPaymentToOrder::make()->action($order, $payment, []);

        $invoice=$order->invoices()->where('invoices.type',InvoiceTypeEnum::INVOICE)->first();
        if($invoice){
            AttachPaymentToInvoice::make()->action($invoice, $payment, []);
        }


        return $payment;
    }

    public function rules(): array
    {
        return [
            'amount'       => ['required', 'decimal:0,2'],
            'reference'    => ['nullable', 'string', 'max:255'],
            'status'       => ['sometimes', 'required', Rule::enum(PaymentStatusEnum::class)],
            'state'        => ['sometimes', 'required', Rule::enum(PaymentStateEnum::class)],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order, PaymentAccount $paymentAccount, array $modelData): Payment
    {
        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $paymentAccount, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, PaymentAccount $paymentAccount, ActionRequest $request): Payment
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $paymentAccount, $this->validatedData);
    }


    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
