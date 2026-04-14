<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Apr 2026 10:18:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait WithDispatchingPercentages
{
    protected function getDispatchingPercentages(HasMany $deliveryNotesItems): array
    {
        $pickingPercentage = 0;
        $packingPercentage = 0;

        // Sum of required minus not picked, guarding nulls and negatives
        $itemsRequired = (int)($deliveryNotesItems
            ->where('delivery_note_items.state', '!=', DeliveryNoteItemStateEnum::CANCELLED)
            ->selectRaw('SUM(GREATEST((quantity_required - COALESCE(quantity_not_picked, 0) - COALESCE(quantity_waiting_warehouse, 0) - COALESCE(quantity_waiting_crm, 0)   ), 0)) as total')
            ->value('total') ?? 0);

        $itemsPicked = (float)$deliveryNotesItems->sum('quantity_picked');
        $itemsPacked = (float)$deliveryNotesItems->sum('quantity_packed');

        // Picking percentage: picked vs. required
        if ($itemsRequired > 0) {
            $pickingPercentage = min(($itemsPicked / $itemsRequired) * 100, 100);
        }

        // Packing percentage: packed vs. picked
        if ($itemsPicked > 0) {
            $packingPercentage = min(($itemsPacked / $itemsPicked) * 100, 100);
        }

        return [
            'quantity_picked'    => $itemsPicked,
            'quantity_packed'    => $itemsPacked,
            'picking_percentage' => round($pickingPercentage, 2),
            'packing_percentage' => round($packingPercentage, 2),
        ];
    }
}
