<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Jul 2026 10:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Consumables (address labels, leaflets, …) the packer must add to the box, totalled over the whole
 * delivery note.
 *
 * Each org stock declares what one ordered product using it needs. It sits on the org stock because
 * the consumable is a property of the warehouse that packs the parcel, not of the shop that sold it,
 * and one organisation serves many shops.
 *
 * The total follows what was actually picked, not what was ordered, so a line that was out of stock
 * does not put its consumable in the box. A part-picked line rounds up to a whole consumable, half
 * a label not being a thing.
 */
class GetDeliveryNoteConsumables
{
    use AsObject;

    /**
     * @return array<int, array{code: string, quantity: float}>
     */
    public function handle(DeliveryNote $deliveryNote): array
    {
        $rows = DB::select(
            "
            WITH picked AS (
                SELECT
                    delivery_note_items.transaction_id,
                    delivery_note_items.org_stock_id,
                    min(delivery_note_items.quantity_picked / nullif(delivery_note_items.quantity_required, 0)) AS ratio
                FROM delivery_note_items
                WHERE delivery_note_items.delivery_note_id = ?
                  AND delivery_note_items.transaction_id IS NOT NULL
                  AND delivery_note_items.org_stock_id IS NOT NULL
                GROUP BY 1, 2
            )
            SELECT
                consumable->>'code' AS code,
                ceil(sum((consumable->>'quantity')::numeric * transactions.quantity_ordered * picked.ratio)) AS quantity
            FROM picked
            JOIN transactions ON transactions.id = picked.transaction_id
            JOIN org_stocks ON org_stocks.id = picked.org_stock_id
            CROSS JOIN LATERAL jsonb_array_elements(org_stocks.consumables) AS consumable
            GROUP BY 1
            HAVING ceil(sum((consumable->>'quantity')::numeric * transactions.quantity_ordered * picked.ratio)) > 0
            ORDER BY 1
            ",
            [$deliveryNote->id]
        );

        return array_map(fn ($row) => [
            'code'     => $row->code,
            'quantity' => (float) $row->quantity,
        ], $rows);
    }
}
