<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 25 Jan 2024 16:42:23 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Fulfilment\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\Pallet;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilmentHydratePallets
{
    use AsAction;
    use WithEnumStats;

    private Fulfilment $fulfilment;
    public function __construct(Fulfilment $fulfilment)
    {
        $this->fulfilment = $fulfilment;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->fulfilment->id))->dontRelease()];
    }

    public function handle(Fulfilment $fulfilment): void
    {
        $stats = [
            'number_pallets' => Pallet::where('fulfilment_id', $fulfilment->id)->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'state',
            enum: PalletStateEnum::class,
            models: Pallet::class,
            where: function ($q) use ($fulfilment) {
                $q->where('fulfilment_id', $fulfilment->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'status',
            enum: PalletStatusEnum::class,
            models: Pallet::class,
            where: function ($q) use ($fulfilment) {
                $q->where('fulfilment_id', $fulfilment->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallets',
            field: 'type',
            enum: PalletTypeEnum::class,
            models: Pallet::class,
            where: function ($q) use ($fulfilment) {
                $q->where('fulfilment_id', $fulfilment->id);
            }
        ));
        $fulfilment->stats()->update($stats);
    }
}
