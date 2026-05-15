<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Apr 2024 11:09:01 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Fulfilment\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\PalletReturn;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilmentHydratePalletReturns implements ShouldBeUnique
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
            'number_pallet_returns' => PalletReturn::where('fulfilment_id', $fulfilment->id)->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallet_returns',
            field: 'state',
            enum: PalletReturnStateEnum::class,
            models: PalletReturn::class,
            where: function ($q) use ($fulfilment) {
                $q->where('fulfilment_id', $fulfilment->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallet_returns',
            field: 'state',
            enum: PalletReturnStateEnum::class,
            models: PalletReturn::class,
            where: function ($q) use ($fulfilment) {
                $q->where('fulfilment_id', $fulfilment->id)
                    ->where('type', PalletReturnTypeEnum::STORED_ITEM);
            },
            modelCustomLabel: 'pallet_returns_items'
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallet_returns',
            field: 'state',
            enum: PalletReturnStateEnum::class,
            models: PalletReturn::class,
            where: function ($q) use ($fulfilment) {
                $q->where('fulfilment_id', $fulfilment->id)
                    ->where('type', PalletReturnTypeEnum::PALLET);
            },
            modelCustomLabel: 'pallet_returns_pallet'
        ));

        $stats['number_pallet_returns_pallet_state_dispatched_today'] = PalletReturn::where('fulfilment_id', $fulfilment->id)
            ->where('type', PalletReturnTypeEnum::PALLET)
            ->where('state', PalletReturnStateEnum::DISPATCHED)
            ->whereDate('dispatched_at', Carbon::today())
            ->count();

        $stats['number_pallet_returns_items_state_dispatched_today'] = PalletReturn::where('fulfilment_id', $fulfilment->id)
            ->where('type', PalletReturnTypeEnum::STORED_ITEM)
            ->where('state', PalletReturnStateEnum::DISPATCHED)
            ->whereDate('dispatched_at', Carbon::today())
            ->count();

        $fulfilment->stats()->update($stats);
    }
}
