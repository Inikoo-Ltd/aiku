<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Aug 2025 12:11:37 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateCreditTransactions;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class RefundPayment extends OrgAction
{
    use WithActionUpdate;


    private Payment $payment;

    public function handle(Payment $payment, array $modelData): void
    {
        $maxToRefund  = $payment->amount;
        $type         = Arr::get($modelData, 'type_refund', 'payment');
        $refundAmount = Arr::get($modelData, 'amount');



        if (!blank($payment->invoices)) {
            $paymentAmount = $payment->invoices->sum('payment_amount');
            $totalAmount   = $payment->invoices->sum('total_amount');

            $maxToRefund = $paymentAmount - $totalAmount;
        }

        $amountPayPerRefund = min($refundAmount, $maxToRefund);

        $refundPayment = StorePayment::make()->action($payment->customer, $payment->paymentAccount, [
            'type'                    => PaymentTypeEnum::REFUND,
            'original_payment_id'     => $payment->id,
            'amount'                  => -abs($amountPayPerRefund),
            'payment_account_shop_id' => $payment->payment_account_shop_id
        ]);

        if ($type === 'credit') {
            StoreCreditTransaction::make()->action($payment->customer, [
                'amount' => abs($amountPayPerRefund),
                'date'   => now(),
                'type'   => CreditTransactionTypeEnum::MONEY_BACK
            ]);
        }

        $totalRefund = abs($payment->total_refund) + abs($amountPayPerRefund);
        $this->update($payment, [
            'total_refund' => $totalRefund,
            'with_refund'  => true
        ]);


        $this->processOnlineRefunds($payment, $refundPayment);


        if ($type === 'credit') {
            if ($this->asAction) {
                CustomerHydrateCreditTransactions::run($payment->customer);
            } else {
                CustomerHydrateCreditTransactions::dispatch($payment->customer);
            }
        }
    }


    public function processInvoices(ActionRequest $request): void
    {

    }

    public function processOrders(ActionRequest $request): void
    {

    }

    public function processCreditTransactions(ActionRequest $request): void
    {

    }

    public function processOnlineRefunds(Payment $payment,Payment $refundPayment): void
    {
        if ($payment->paymentAccount->type === PaymentAccountTypeEnum::CHECKOUT) {
            $ref = RefundPaymentApiRequest::run($refundPayment, $payment->reference);

            if (!Arr::get($ref, 'error')) {
                $this->update($refundPayment, [
                    'state'  => PaymentStateEnum::COMPLETED,
                    'status' => PaymentStatusEnum::SUCCESS
                ]);
            }
        }
    }


    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric','gt:0','lte:'.$this->payment->amount-$this->payment->total_refund],
            'reason' => ['required', 'string', 'max:1000']
        ];
    }

    public function asController(Organisation $organisation, Payment $payment, ActionRequest $request): void
    {
        $this->payment= $payment;
        $this->initialisation($organisation, $request);

        $this->handle($payment, $this->validatedData);
    }
}
