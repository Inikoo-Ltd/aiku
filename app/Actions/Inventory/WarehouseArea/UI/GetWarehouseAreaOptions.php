<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseArea\UI;

use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWarehouseAreaOptions
{
    use AsObject;

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    public function handle(Warehouse $warehouse): array
    {
        $options = $warehouse->warehouseAreas()
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn ($warehouseArea) => [
                'label' => $warehouseArea->code.' - '.$warehouseArea->name,
                'value' => $warehouseArea->id,
            ])
            ->all();

        array_unshift($options, ['label' => __('No area'), 'value' => '']);

        return $options;
    }
}
