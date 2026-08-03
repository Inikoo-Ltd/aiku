<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:53:53 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Platform\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dropshipping\Platform;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PlatformHydrateOrders implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(?int $platformId): string
    {
        if (!$platformId) {
            $platformId = 'empty';
        }

        return $platformId;
    }

    public function handle(?int $platformId): void
    {
        if (!$platformId) {
            return;
        }

        $platform = Platform::find($platformId);
        if (!$platform) {
            return;
        }

        $stats = [
            'number_orders' => DB::connection('aiku_no_sticky')->table('orders')
                ->where('platform_id', $platformId)
                ->count(),
        ];


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'orders',
                field: 'state',
                enum: OrderStateEnum::class,
                models: Order::class,
                connection: 'aiku_no_sticky'
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'orders',
                field: 'handing_type',
                enum: OrderHandingTypeEnum::class,
                models: Order::class,
                connection: 'aiku_no_sticky'
            )
        );

        $platform->stats()->update($stats);
    }
}
