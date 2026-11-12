<?php

/*
 * Author Andi Ferdiawan
 * Created on 11-11-2026
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Locations;

use App\Actions\Inventory\Warehouse\UI\GetWarehouseEmptyLocationsPanel;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMobileEmptyLocationsRemoval
{
    use AsObject;

    /**
     * @return array{enabled: bool, can_edit: bool, last_change: array{by: string|null, at: string|null}|null, to_remove: int, removed_last_7_days: int, max_per_run: int}
     */
    public function handle(Warehouse $warehouse, bool $canEdit): array
    {
        $panel = GetWarehouseEmptyLocationsPanel::run($warehouse, $canEdit);

        return [
            'enabled'             => (bool) $panel['enabled'],
            'can_edit'            => $canEdit,
            'last_change'         => $panel['last_change'] ? [
                'by' => $panel['last_change']['by'],
                'at' => $panel['last_change']['at'] ? Carbon::parse($panel['last_change']['at'])->toIso8601String() : null,
            ] : null,
            'to_remove'           => (int) $panel['to_remove'],
            'removed_last_7_days' => (int) $panel['removed_last_7_days'],
            'max_per_run'         => (int) $panel['max_per_run'],
        ];
    }
}
