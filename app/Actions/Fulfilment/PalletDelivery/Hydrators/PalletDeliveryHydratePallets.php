<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 20 Jul 2023 09:57:45 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class PalletDeliveryHydratePallets implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(PalletDelivery $palletDelivery): string
    {
        return $palletDelivery->id;
    }

    public function handle(PalletDelivery $palletDelivery): void
    {
        $stats = [
            'number_pallets'                   => Pallet::where('pallet_delivery_id', $palletDelivery->id)->count(),
            'number_pallets_with_stored_items' => Pallet::where('pallet_delivery_id', $palletDelivery->id)->where('with_stored_items', '=', true)->count(),
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'state',
            enum: PalletStateEnum::class,
            models: Pallet::class,
            where: function ($q) use ($palletDelivery) {
                $q->where('pallet_delivery_id', $palletDelivery->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'status',
            enum: PalletStatusEnum::class,
            models: Pallet::class,
            where: function ($q) use ($palletDelivery) {
                $q->where('pallet_delivery_id', $palletDelivery->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'type',
            enum: PalletTypeEnum::class,
            models: Pallet::class,
            where: function ($q) use ($palletDelivery) {
                $q->where('pallet_delivery_id', $palletDelivery->id);
            }
        ));

        $palletDelivery->stats()->update($stats);
    }
}
