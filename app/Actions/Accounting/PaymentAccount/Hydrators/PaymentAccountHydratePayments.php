<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 11:37:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentAccount\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class PaymentAccountHydratePayments
{
    use AsAction;
    use WithEnumStats;


    private PaymentAccount $paymentAccount;

    public function __construct(PaymentAccount $paymentAccount)
    {
        $this->paymentAccount = $paymentAccount;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->paymentAccount->id))->dontRelease()];
    }

    public function handle(PaymentAccount $paymentAccount): void
    {
        $amountOrganisationCurrencySuccessfullyPaid = $paymentAccount->payments()
            ->where('type', 'payment')
            ->where('status', 'success')
            ->sum('sales_org_currency_');
        $amountOrganisationCurrencyRefunded         = $paymentAccount->payments()
            ->where('payments.type', 'refund')
            ->where('status', 'success')
            ->sum('sales_org_currency_');

        $stats = [
            'number_payments'                => $paymentAccount->payments()->count(),
            'sales_org_currency_'                     => $amountOrganisationCurrencySuccessfullyPaid + $amountOrganisationCurrencyRefunded,
            'sales_org_currency_successfully_paid'   => $amountOrganisationCurrencySuccessfullyPaid,
            'sales_org_currency_refunded'            => $amountOrganisationCurrencyRefunded
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'payments',
                field: 'type',
                enum: PaymentTypeEnum::class,
                models: Payment::class,
                where: function ($q) use ($paymentAccount) {
                    $q->where('payment_account_id', $paymentAccount->id);
                }
            )
        );


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'payments',
                field: 'state',
                enum: PaymentStateEnum::class,
                models: Payment::class,
                where: function ($q) use ($paymentAccount) {
                    $q->where('payment_account_id', $paymentAccount->id);
                }
            )
        );

        foreach (PaymentTypeEnum::cases() as $type) {
            $stats = array_merge(
                $stats,
                $this->getEnumStats(
                    model: "payments_type_{$type->snake()}",
                    field: 'state',
                    enum: PaymentStateEnum::class,
                    models: Payment::class,
                    where: function ($q) use ($paymentAccount, $type) {
                        $q->where('payment_account_id', $paymentAccount->id)->where('type', $type->value);
                    }
                )
            );
        }

        $paymentAccount->stats()->update($stats);
    }


}
