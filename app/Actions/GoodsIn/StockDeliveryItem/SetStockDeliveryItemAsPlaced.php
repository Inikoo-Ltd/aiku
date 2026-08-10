<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 28 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\OrgAction;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SetStockDeliveryItemAsPlaced extends OrgAction
{
    use WithProcurementEditAuthorisation;
    public function handle(StockDeliveryItem $stockDeliveryItem, array $modelData): StockDeliveryItem
    {
        $remaining = (float) $stockDeliveryItem->unit_quantity_checked - (float) $stockDeliveryItem->unit_quantity_placed;

        data_set($modelData, 'quantity', $remaining);

        return UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, $modelData);
    }

    public function rules(): array
    {
        return [
            'location_org_stock_id' => ['sometimes', Rule::Exists('location_org_stocks', 'id')],
        ];
    }

    public function asController(StockDeliveryItem $stockDeliveryItem, ActionRequest $request): void
    {
        $this->initialisation($stockDeliveryItem->organisation, $request);

        $this->handle($stockDeliveryItem, $this->validatedData);
    }

    public function action(StockDeliveryItem $stockDeliveryItem, array $modelData): StockDeliveryItem
    {
        $this->asAction = true;
        $this->initialisation($stockDeliveryItem->organisation, $modelData);

        return $this->handle($stockDeliveryItem, $this->validatedData);
    }
}
