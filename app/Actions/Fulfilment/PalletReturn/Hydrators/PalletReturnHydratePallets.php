<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 20 Jul 2023 09:57:45 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn\Hydrators;

use App\Actions\HydrateModel;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class PalletReturnHydratePallets extends HydrateModel
{
    use AsAction;
    use WithEnumStats;

    private PalletReturn $palletReturn;
    public function __construct(PalletReturn $palletReturn)
    {
        $this->palletReturn = $palletReturn;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->palletReturn->id))->dontRelease()];
    }

    public function handle(PalletReturn $palletReturn): void
    {
        $stats = [
            'number_pallets'                   => Pallet::where('pallet_return_id', $palletReturn->id)->count(),
            'number_pallets_with_stored_items' => Pallet::where('pallet_return_id', $palletReturn->id)->where('with_stored_items', '=', true)->count(),
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'state',
            enum: PalletStateEnum::class,
            models: Pallet::class,
            where: function ($q) use ($palletReturn) {
                $q->where('pallet_return_id', $palletReturn->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'status',
            enum: PalletStatusEnum::class,
            models: Pallet::class,
            where: function ($q) use ($palletReturn) {
                $q->where('pallet_return_id', $palletReturn->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'type',
            enum: PalletTypeEnum::class,
            models: Pallet::class,
            where: function ($q) use ($palletReturn) {
                $q->where('pallet_return_id', $palletReturn->id);
            }
        ));

        $palletReturn->stats()->update($stats);
    }
}
