<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Capacity;

use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class IsWarehouseOverCapacity
{
    use AsObject;

    public const int CACHE_SECONDS = 300;

    public function handle(Warehouse $warehouse, string $stream): bool
    {
        return Cache::remember(
            'warehouse-over-capacity:'.$warehouse->id.':'.$stream,
            self::CACHE_SECONDS,
            fn () => GetWarehouseCapacity::run($warehouse, $stream)['status'] === 'over'
        );
    }
}
