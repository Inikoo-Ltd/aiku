<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 11:37:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentAccount\Hydrators;

use App\Actions\Traits\Hydrators\WithPaymentAggregators;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class PaymentAccountHydratePayments implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithPaymentAggregators;

    public function getJobUniqueId(PaymentAccount $paymentAccount): string
    {
        return $paymentAccount->id;
    }


    public function handle(PaymentAccount $paymentAccount): void
    {
        $stats = array_merge(
            [
                'number_payments' => $paymentAccount->payments()->count()
            ],
            $this->paidAmounts($paymentAccount, 'org_amount'),
            $this->paidAmounts($paymentAccount, 'grp_amount'),
        );


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
