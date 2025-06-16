<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 May 2024 15:19:54 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Fulfilment\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\Fulfilment\FulfilmentCustomer\FulfilmentCustomerStatusEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilmentHydrateCustomers implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;


    public function getJobUniqueId(Fulfilment $fulfilment): string
    {
        return $fulfilment->id;
    }

    public function handle(Fulfilment $fulfilment): void
    {
        $stats = [
            'number_customers_interest_pallets_storage' => $fulfilment->fulfilmentCustomers()->where('pallets_storage', true)->count(),
            'number_customers_interest_items_storage'   => $fulfilment->fulfilmentCustomers()->where('items_storage', true)->count(),
            'number_customers_interest_dropshipping'    => $fulfilment->fulfilmentCustomers()->where('dropshipping', true)->count(),
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'customers',
            field: 'status',
            enum: FulfilmentCustomerStatusEnum::class,
            models: FulfilmentCustomer::class,
            where: function ($q) use ($fulfilment) {
                $q->where('fulfilment_id', $fulfilment->id)
                ->where('status', '!=', FulfilmentCustomerStatusEnum::ACTIVE->value); // exclude active customers cause the active should be already approved
            }
        ));

        $stats['number_customers_status_active'] = $fulfilment->fulfilmentCustomers()->where('status', FulfilmentCustomerStatusEnum::ACTIVE->value)->whereHas('customer', function ($query) {
            $query->where('status', CustomerStatusEnum::APPROVED->value);
        })->count();

        $fulfilment->stats()->update($stats);
    }


}
