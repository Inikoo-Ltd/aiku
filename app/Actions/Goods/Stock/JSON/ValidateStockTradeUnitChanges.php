<?php

/*
 * Author Louis Perez
 * Created on 24-07-2026-17h-21m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\Stock\JSON;

use App\Actions\Dispatching\DeliveryNoteItem\SyncDeliveryNoteItemsRequiredPickQuantity;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Goods\Stock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class ValidateStockTradeUnitChanges extends OrgAction
{
    use WithGoodsEditAuthorisation;

    /**
     * Delivery notes that keep their picking untouched, but whose quantities no longer match the new composition.
     */
    public const AFFECTED_STATES = [
        DeliveryNoteItemStateEnum::HANDLING_BLOCKED,
        DeliveryNoteItemStateEnum::PICKED,
        DeliveryNoteItemStateEnum::PACKING,
        DeliveryNoteItemStateEnum::PACKED,
    ];

    public function handle(Stock $stock): array
    {
        return [
            'to_be_modified' => $this->getDeliveryNotes($stock, SyncDeliveryNoteItemsRequiredPickQuantity::SYNCED_STATES),
            'to_be_affected' => $this->getDeliveryNotes($stock, self::AFFECTED_STATES),
        ];
    }

    private function getDeliveryNotes(Stock $stock, array $itemStates): Collection
    {
        return DB::table('delivery_note_items')
            ->join('delivery_notes', 'delivery_notes.id', 'delivery_note_items.delivery_note_id')
            ->join('shops', 'shops.id', 'delivery_notes.shop_id')
            ->whereIn('delivery_note_items.state', collect($itemStates)->map(fn ($state) => $state->value))
            ->whereIn('delivery_note_items.org_stock_id', $stock->orgStocks->pluck('id'))
            ->distinct()
            ->select([
                'delivery_notes.id',
                'delivery_notes.reference',
                'delivery_notes.state',
                'shops.name as shop_name',
                'delivery_notes.is_premium_dispatch',
            ])
            ->get();
    }

    public function asController(Stock $stock, ActionRequest $request): array
    {
        $this->initialisationFromGroup($stock->group, $request);

        return $this->handle($stock);
    }

    public function jsonResponse(array $result): array
    {
        return $result;
    }
}
