<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Apr 2024 11:09:01 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Fulfilment\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilmentHydratePalletDeliveries implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(Fulfilment $fulfilment): string
    {
        return $fulfilment->id;
    }

    public function handle(Fulfilment $fulfilment): void
    {
        $stats = [
            'number_pallet_deliveries' => PalletDelivery::where('fulfilment_id', $fulfilment->id)->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallet_deliveries',
            field: 'state',
            enum: PalletDeliveryStateEnum::class,
            models: PalletDelivery::class,
            where: function ($q) use ($fulfilment) {
                $q->where('fulfilment_id', $fulfilment->id);
            }
        ));

        $fulfilment->stats()->update($stats);
    }
}
