<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Mar 2023 04:18:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerTradeStateEnum;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateCustomers implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }


    public function handle(Shop $shop): void
    {
        $stats = [
            'number_customers' => $shop->customers()->count(),
        ];


        $stats = array_merge($stats, $this->getEnumStats(
            model: 'customers',
            field: 'state',
            enum: CustomerStateEnum::class,
            models: Customer::class,
            where: function ($q) use ($shop) {
                $q->where('shop_id', $shop->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model: 'customers',
            field: 'trade_state',
            enum: CustomerTradeStateEnum::class,
            models: Customer::class,
            where: function ($q) use ($shop) {
                $q->where('shop_id', $shop->id);
            }
        ));


        $shop->crmStats()->update($stats);
    }
}
