<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 11:29:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentServiceProvider\Hydrators;

use App\Actions\Traits\Hydrators\WithPaymentAggregators;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentServiceProvider;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class PaymentServiceProviderHydratePayments implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithPaymentAggregators;

    public function getJobUniqueId(PaymentServiceProvider $paymentServiceProvider): string
    {
        return $paymentServiceProvider->id;
    }

    public function handle(PaymentServiceProvider $paymentServiceProvider): void
    {


        $stats = array_merge(
            [
                'number_payments' => $paymentServiceProvider->payments()->count()
            ],
            $this->paidAmounts($paymentServiceProvider, 'grp_amount'),
        );


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'payments',
                field: 'type',
                enum: PaymentTypeEnum::class,
                models: Payment::class,
                where: function ($q) use ($paymentServiceProvider) {
                    $q->where('payment_service_provider_id', $paymentServiceProvider->id);
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
                where: function ($q) use ($paymentServiceProvider) {
                    $q->where('payment_service_provider_id', $paymentServiceProvider->id);
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
                    where: function ($q) use ($paymentServiceProvider, $type) {
                        $q->where('payment_service_provider_id', $paymentServiceProvider->id)->where('type', $type->value);
                    }
                )
            );
        }

        $paymentServiceProvider->stats()->update($stats);
    }


}
