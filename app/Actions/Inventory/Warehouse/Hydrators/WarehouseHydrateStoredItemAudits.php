<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 20:29:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Warehouse\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Inventory\Warehouse;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WarehouseHydrateStoredItemAudits implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Warehouse $warehouse): string
    {
        return $warehouse->id;
    }

    public function handle(Warehouse $warehouse): void
    {
        $stats = [
            'number_stored_item_audits' => StoredItemAudit::where('warehouse_id', $warehouse->id)->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'stored_item_audits',
                field: 'state',
                enum: StoredItemAuditStateEnum::class,
                models: StoredItemAudit::class,
                where: function ($q) use ($warehouse) {
                    $q->where('warehouse_id', $warehouse->id);
                }
            )
        );

        $warehouse->stats()->update($stats);
    }
}
