<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 25 Jan 2024 16:42:23 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentCustomer\Hydrators;

use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateFulfilmentCustomers;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilmentCustomerHydratePallets implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(FulfilmentCustomer $fulfilmentCustomer): string
    {
        return $fulfilmentCustomer->id;
    }

    public function handle(FulfilmentCustomer $fulfilmentCustomer): void
    {
        $stats = [
            'number_pallets' => $fulfilmentCustomer->pallets()->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'state',
            enum: PalletStateEnum::class,
            models: Pallet::class,
            where: function ($q) use ($fulfilmentCustomer) {
                $q->where('fulfilment_customer_id', $fulfilmentCustomer->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'status',
            enum: PalletStatusEnum::class,
            models: Pallet::class,
            where: function ($q) use ($fulfilmentCustomer) {
                $q->where('fulfilment_customer_id', $fulfilmentCustomer->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'type',
            enum: PalletTypeEnum::class,
            models: Pallet::class,
            where: function ($q) use ($fulfilmentCustomer) {
                $q->where('fulfilment_customer_id', $fulfilmentCustomer->id);
            }
        ));


        $fulfilmentCustomer->update($stats);

        OrganisationHydrateFulfilmentCustomers::dispatch($fulfilmentCustomer->organisation);


    }
}
