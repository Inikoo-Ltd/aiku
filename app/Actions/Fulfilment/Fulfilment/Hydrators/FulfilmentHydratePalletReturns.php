<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Apr 2024 11:09:01 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Fulfilment\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilmentHydratePalletReturns
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

        $fulfilment->stats()->update($stats);
    }
}
