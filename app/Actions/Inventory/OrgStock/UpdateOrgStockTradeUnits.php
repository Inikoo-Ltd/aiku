<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Catalogue\Product\SyncProductOrgStocksFromTradeUnits;
use App\Actions\Dispatching\DeliveryNoteItem\SyncDeliveryNoteItemsRequiredPickQuantity;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Models\Inventory\OrgStock;
use Illuminate\Support\Facades\Bus;
use Lorisleiva\Actions\ActionRequest;

/**
 * Edits one warehouse's OS-TU composition (its packed_in reality) and pushes the change
 * through the same pipes the stock editor uses: org stock pivot, packed_in, the products'
 * pick mapping and any delivery note items still ahead of picking.
 */
class UpdateOrgStockTradeUnits extends OrgAction
{
    use WithGoodsEditAuthorisation;

    public function handle(OrgStock $orgStock, array $modelData): OrgStock
    {
        $tradeUnitsData = collect($modelData['trade_units'])
            ->mapWithKeys(fn ($tradeUnit) => [$tradeUnit['id'] => ['quantity' => $tradeUnit['quantity']]])
            ->toArray();

        $orgStock = SyncOrgStockTradeUnits::run($orgStock, $tradeUnitsData);

        $orgStock->unsetRelation('products');
        $jobs = $orgStock->products
            ->map(fn ($product) => SyncProductOrgStocksFromTradeUnits::makeJob($product))
            ->push(SyncDeliveryNoteItemsRequiredPickQuantity::makeJob($orgStock))
            ->all();

        Bus::chain($jobs)->dispatch();

        return $orgStock;
    }

    public function rules(): array
    {
        return [
            'trade_units'            => ['required', 'array'],
            'trade_units.*.id'       => ['required', 'exists:trade_units,id'],
            'trade_units.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function asController(OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->initialisationFromGroup($orgStock->group, $request);

        return $this->handle($orgStock, $this->validatedData);
    }
}
