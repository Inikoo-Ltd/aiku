<?php

/*
 * author Arya Permana - Kirin
 * created on 17-02-2025-15h-44m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\PaymentAccount\Hydrators;

use App\Actions\Traits\Hydrators\WithPaymentAggregators;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentAccountShop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class PaymentAccountHydratePAS implements ShouldBeUnique
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

        $stats = [
            'number_pas' => $paymentAccount->paymentAccountShops()->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'pas',
                field: 'payment_account_shop.state',
                enum: PaymentAccountShopStateEnum::class,
                models: PaymentAccountShop::class,
                where: function ($q) use ($paymentAccount) {
                    $q->where('payment_account_id', $paymentAccount->id);
                },
                fieldStatsLabel: 'state'
            )
        );

        $paymentAccount->stats()->update($stats);
    }


}
