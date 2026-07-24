<?php

/*
 * Author Louis Perez
 * Created on 24-07-2026-17h-21m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\Stock\JSON;

use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Http\Resources\Dispatching\DeliveryNotesResource;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Goods\Stock;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class ValidateStockTradeUnitChanges extends OrgAction
{
    public function handle(Stock $stock): array
    {
        $query =  function (array $statesIn, array $orgStockId) {
            return DB::table('delivery_note_items')
                ->leftJoin('delivery_notes as dn', 'dn.id', 'delivery_note_items.delivery_note_id')
                ->whereIn('dn.state', $statesIn)
                ->whereIn('delivery_note_items.org_stock_id', $orgStockId)
                ->where('dn.created_at', '>=', now()->subMonth())
                ->pluck('dn.id');
        };

        $orgStocksId = $stock->orgStocks->pluck('id')->toArray();
        $selects = [
            'delivery_notes.id',
            'delivery_notes.slug',
            'delivery_notes.reference',
            'delivery_notes.date',
            'delivery_notes.state',
            'delivery_notes.type',
            'delivery_notes.created_at',
            'delivery_notes.updated_at',
            'delivery_notes.is_premium_dispatch',
            'shops.slug as shop_slug',
            'shops.name as shop_name',
        ];

        $deliveryNoteWillBeModified = DeliveryNote::whereIn('delivery_notes.id', $query([
                DeliveryNoteStateEnum::UNASSIGNED,
                DeliveryNoteStateEnum::QUEUED,
                DeliveryNoteStateEnum::HANDLING,
            ], $orgStocksId))
            ->leftJoin('shops', 'shops.id', 'delivery_notes.shop_id')
            ->select($selects)
            ->get();

        $deliveryNoteWillBeAffected = DeliveryNote::whereIn('delivery_notes.id', $query([
                DeliveryNoteItemStateEnum::HANDLING_BLOCKED,
                DeliveryNoteItemStateEnum::PICKED,
                DeliveryNoteItemStateEnum::PACKING,
                DeliveryNoteItemStateEnum::PACKED,
            ], $orgStocksId))
            ->leftJoin('shops', 'shops.id', 'delivery_notes.shop_id')
            ->select($selects)
            ->get();

        return [
            'to_be_modified'    => DeliveryNotesResource::collection($deliveryNoteWillBeModified),
            'to_be_affected'    => DeliveryNotesResource::collection($deliveryNoteWillBeAffected)
        ];
    }

    public function rules()
    {
        return [
            'trade_units'   => ['sometimes', 'array', 'nullable'],
        ];
    }

    public function asController(Stock $stock, ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($stock);
    }

    public function jsonResponse(array $result)
    {
        return $result;
    }
}
