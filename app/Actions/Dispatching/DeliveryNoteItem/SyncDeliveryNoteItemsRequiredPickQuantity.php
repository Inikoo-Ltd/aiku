<?php

/*
 * Author Louis Perez
 * Created on 24-07-2026-15h-49m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

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
            ->with('transaction')
            ->whereIn('state', collect(self::SYNCED_STATES)->merge(self::DIRTY_STATES)->map(fn ($state) => $state->value))
            ->get();

        foreach ($deliveryNoteItems as $deliveryNoteItem) {
            $quantityRequired = $this->getQuantityRequired($orgStock, $deliveryNoteItem);

            if (is_null($quantityRequired)) {
                continue;
            }

            if (in_array($deliveryNoteItem->state, self::DIRTY_STATES)) {
                if ($quantityRequired == $deliveryNoteItem->quantity_required) {
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

            if ($quantityRequired == $deliveryNoteItem->quantity_required) {
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

        return $quantityPerProduct * $transaction->quantity_ordered;
    }
}
