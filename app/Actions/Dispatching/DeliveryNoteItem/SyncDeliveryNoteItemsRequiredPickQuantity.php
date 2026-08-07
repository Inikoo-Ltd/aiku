<?php

/*
 * Author Louis Perez
 * Created on 24-07-2026-15h-49m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateCompositionDirtyItems;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\OrgStock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncDeliveryNoteItemsRequiredPickQuantity
{
    use AsAction;

    /**
     * Delivery note items still ahead of picking, so their required quantity can be recalculated in place.
     */
    public const SYNCED_STATES = [
        DeliveryNoteItemStateEnum::UNASSIGNED,
        DeliveryNoteItemStateEnum::QUEUED,
        DeliveryNoteItemStateEnum::HANDLING,
    ];

    /**
     * Physical work already happened under the old composition, so the numbers cannot be
     * rewritten: these items are flagged dirty and a human rolls the picking back (the
     * flag then offers applying the new composition) or confirms what was actually packed.
     */
    public const DIRTY_STATES = [
        DeliveryNoteItemStateEnum::HANDLING_BLOCKED,
        DeliveryNoteItemStateEnum::PICKED,
        DeliveryNoteItemStateEnum::PACKING,
        DeliveryNoteItemStateEnum::PACKED,
    ];

    public function handle(OrgStock $orgStock): void
    {
        $deliveryNoteItems = $orgStock->deliveryNoteItems()
            ->with('transaction.historicAsset', 'transaction.model')
            ->whereIn('state', collect(self::SYNCED_STATES)->merge(self::DIRTY_STATES)->map(fn ($state) => $state->value))
            ->get();

        $touchedDeliveryNoteIds = [];

        foreach ($deliveryNoteItems as $deliveryNoteItem) {
            $touchedDeliveryNoteIds[$deliveryNoteItem->delivery_note_id] = true;
            $quantityRequired = $this->getQuantityRequired($orgStock, $deliveryNoteItem);

            if (is_null($quantityRequired)) {
                continue;
            }

            /*
             * Compared at the precision both columns can hold: a pick in twelfths differs
             * from itself in the last decimal otherwise and stays flagged forever.
             */
            $unchanged = abs($quantityRequired - (float)$deliveryNoteItem->quantity_required) < 0.000001;

            if (in_array($deliveryNoteItem->state, self::DIRTY_STATES)) {
                if ($unchanged) {
                    if ($deliveryNoteItem->composition_dirty_at) {
                        $deliveryNoteItem->update([
                            'composition_dirty_at'                => null,
                            'composition_dirty_quantity_required' => null,
                        ]);
                    }
                } else {
                    $deliveryNoteItem->update([
                        'composition_dirty_at'                => Carbon::now(),
                        'composition_dirty_quantity_required' => $quantityRequired,
                    ]);
                }

                continue;
            }

            if ($unchanged) {
                if ($deliveryNoteItem->composition_dirty_at) {
                    $deliveryNoteItem->update([
                        'composition_dirty_at'                => null,
                        'composition_dirty_quantity_required' => null,
                    ]);
                }
                continue;
            }

            UpdateDeliveryNoteItem::make()->action($deliveryNoteItem, [
                'quantity_required'          => $quantityRequired,
                'original_quantity_required' => $quantityRequired,
                'estimated_required_weight'  => (int)($quantityRequired * ($orgStock->stock?->gross_weight ?? 0)),
            ], strict: false);

            if ($deliveryNoteItem->composition_dirty_at) {
                $deliveryNoteItem->update([
                    'composition_dirty_at'                => null,
                    'composition_dirty_quantity_required' => null,
                ]);
            }
        }

        foreach (array_keys($touchedDeliveryNoteIds) as $deliveryNoteId) {
            DeliveryNoteHydrateCompositionDirtyItems::run($deliveryNoteId);
        }
    }

    public function getQuantityRequired(OrgStock $orgStock, DeliveryNoteItem $deliveryNoteItem): ?float
    {
        $transaction = $deliveryNoteItem->transaction;

        if (!$transaction || $transaction->model_type != class_basename(Product::class)) {
            return null;
        }

        $quantityPerProduct = DB::table('product_has_org_stocks')
            ->where('product_id', $transaction->model_id)
            ->where('org_stock_id', $orgStock->id)
            ->value('quantity');

        if (is_null($quantityPerProduct)) {
            return null;
        }

        /*
         * The order was placed against a pack of a certain size, and the historic asset on
         * the transaction remembers it. Redefining the product later — MMC-10 went from a
         * 3 piece box at 5.85 to a 6 piece box at 7.60 — must not double what an already
         * sold box means, so the live composition is scaled back to the pack that was
         * bought. A composition corrected without touching units gives a ratio of 1 and
         * still propagates, which is the whole point of this sync.
         */
        $unitsSold = (float)($transaction->historicAsset?->units ?? 0);
        $unitsNow  = (float)($transaction->model?->units ?? 0);
        $packRatio = ($unitsSold > 0 && $unitsNow > 0) ? $unitsSold / $unitsNow : 1;

        return $quantityPerProduct * $transaction->quantity_ordered * $packRatio;
    }
}
