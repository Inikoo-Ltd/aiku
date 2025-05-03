<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 25 Jan 2024 16:42:23 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Location;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class LocationHydratePallets implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Location $location): string
    {
        return $location->id;
    }

    public function handle(Location $location): void
    {
        $stats = [
            'number_pallets' => Pallet::where('location_id', $location->id)->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'state',
            enum: PalletStateEnum::class,
            models: Pallet::class,
            where: function ($q) use ($location) {
                $q->where('location_id', $location->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'status',
            enum: PalletStatusEnum::class,
            models: Pallet::class,
            where: function ($q) use ($location) {
                $q->where('location_id', $location->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'type',
            enum: PalletTypeEnum::class,
            models: Pallet::class,
            where: function ($q) use ($location) {
                $q->where('location_id', $location->id);
            }
        ));

        $location->stats()->update($stats);
    }
}
