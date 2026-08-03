<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Mar 2023 01:07:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\Hydrators\WithPaymentAggregators;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydratePayments implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithPaymentAggregators;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(?int $shopId): string
    {
        return $shopId ?? 'empty';
    }

    public function handle(?int $shopId): void
    {
        if (!$shopId) {
            return;
        }
        $shop = Shop::on('aiku_no_sticky')->find($shopId);
        if (!$shop) {
            return;
        }

        $stats = array_merge(
            [
                'number_payments' => DB::connection('aiku_no_sticky')->table('payments')->whereNull('deleted_at')->where('shop_id', $shop->id)->count()
            ],
            $this->paidAmounts($shop, 'amount'),
            $this->paidAmounts($shop, 'org_amount'),
            $this->paidAmounts($shop, 'grp_amount'),
        );


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'payments',
                field: 'type',
                enum: PaymentTypeEnum::class,
                models: Payment::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id);
                },
                connection: 'aiku_no_sticky'
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'payments',
                field: 'state',
                enum: PaymentStateEnum::class,
                models: Payment::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id);
                },
                connection: 'aiku_no_sticky'
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
                    where: function ($q) use ($shop, $type) {
                        $q->where('shop_id', $shop->id)->where('type', $type->value);
                    },
                    connection: 'aiku_no_sticky'
                )
            );
        }


        $shop->accountingStats()->update($stats);
    }

}
