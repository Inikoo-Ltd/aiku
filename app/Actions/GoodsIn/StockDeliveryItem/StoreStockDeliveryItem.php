<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:26:37 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithStoreProcurementOrderItem;
use App\Enums\Procurement\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\HistoricSupplierProduct;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class StoreStockDeliveryItem extends OrgAction
{
    use WithNoStrictRules;
    use WithStoreProcurementOrderItem;

    public function handle(StockDelivery $stockDelivery, ?HistoricSupplierProduct $historicSupplierProduct, OrgStock $orgStock, array $modelData): StockDeliveryItem
    {
        $modelData = $this->prepareProcurementOrderItem($stockDelivery, $historicSupplierProduct, $orgStock, $modelData);


        if (Arr::has($modelData, 'gross_amount')) {
            data_set($modelData, 'grp_gross_amount', Arr::get($modelData, 'gross_amount', 0) * Arr::get($modelData, 'grp_exchange', 1));
            data_set($modelData, 'org_gross_amount', Arr::get($modelData, 'gross_amount', 0) * Arr::get($modelData, 'org_exchange', 1));
        }


        /** @var StockDeliveryItem $stockDeliveryItem */
        $stockDeliveryItem = $stockDelivery->items()->create($modelData);

        return $stockDeliveryItem;
    }

    public function rules(): array
    {
        $rules = [
            'unit_quantity' => ['required', 'numeric', 'gte:0'],

        ];

        if (!$this->strict) {
            $rules['state']                 = ['sometimes', 'required', Rule::enum(StockDeliveryItemStateEnum::class)];
            $rules['unit_quantity_checked'] = ['sometimes', 'numeric', 'gte:0'];
            $rules['unit_quantity_placed']  = ['sometimes', 'numeric', 'gte:0'];
            $rules                          = $this->noStrictStoreRules($rules);
        }


        return $rules;
    }

    public function action(StockDelivery $stockDelivery, ?HistoricSupplierProduct $historicSupplierProduct, OrgStock $orgStock, array $modelData, int $hydratorsDelay = 0, bool $strict = true): StockDeliveryItem
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($stockDelivery->organisation, $modelData);

        return $this->handle($stockDelivery, $historicSupplierProduct, $orgStock, $this->validatedData);
    }

}
