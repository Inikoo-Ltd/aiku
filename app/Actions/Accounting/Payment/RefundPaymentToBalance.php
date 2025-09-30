<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Sept 2025 12:49:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Invoice\AttachPaymentToInvoice;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateCreditTransactions;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccountShop;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class RefundPaymentToBalance extends OrgAction
{
    use WithActionUpdate;


    private Payment $payment;


    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Payment $payment, array $modelData): Payment|array
    {
        if ($payment->status !== PaymentStatusEnum::SUCCESS) {
            throw ValidationException::withMessages([
                'error'   => true,
                'message' => __('Payment can not be refunded.').'; '.__('Invalid state'),
                ' '.$payment->state->labels()[$payment->state->value]
            ]);
        }

        /** @var PaymentAccountShop $paymentAccountShop */
        $paymentAccountShop = $payment->shop->paymentAccountShops()->where('type', PaymentAccountTypeEnum::ACCOUNT)->first();

        $refundAmount = -Arr::get($modelData, 'amount');

        $invoice = null;
        if (Arr::has($modelData, 'invoice_id')) {
            $invoice = Invoice::find(Arr::get($modelData, 'invoice_id'));
        }

        return DB::transaction(function () use ($refundAmount, $payment, $paymentAccountShop, $invoice) {
            $refundPayment = StorePayment::make()->action($payment->customer, $paymentAccountShop->paymentAccount, [
                'amount'              => $refundAmount,
                'status'              => PaymentStatusEnum::SUCCESS->value,
                'state'               => PaymentStateEnum::COMPLETED->value,
                'type'                => PaymentTypeEnum::REFUND,
                'original_payment_id' => $payment
            ]);

            StoreCreditTransaction::make()->action($refundPayment->customer, [
                'payment_id' => $refundPayment->id,
                'amount'     => -$refundAmount,
                'date'       => now(),
                'type'       => CreditTransactionTypeEnum::PAY_RETURN
            ]);
            CustomerHydrateCreditTransactions::run($refundPayment->customer);

            $this->update($payment, [
                'total_refund' => $payment->total_refund - $refundAmount,
                'with_refund'  => true
            ]);

            AttachPaymentToInvoice::make()->action($invoice, $refundPayment, []);
            if ($invoice->order) {
                AttachPaymentToOrder::make()->action($invoice->order, $refundPayment, []);
            }

            return $refundPayment;
        });



    }


    public function rules(): array
    {
        return [
            'amount'     => ['required', 'numeric', 'gt:0', 'lte:'.$this->payment->amount - $this->payment->total_refund],
            'invoice_id' => [
                'sometimes',
                'nullable',
                Rule::exists('invoices', 'id')
                    ->where('customer_id', $this->payment->customer_id)
            ]
        ];
    }


    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(Payment $payment, ActionRequest $request): Payment
    {
        $this->payment = $payment;
        $this->initialisation($payment->organisation, $request);

        return $this->handle($payment, $this->validatedData);
    }
}
