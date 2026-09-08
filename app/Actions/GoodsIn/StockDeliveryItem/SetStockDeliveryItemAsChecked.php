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
use Lorisleiva\Actions\ActionRequest;

class SetStockDeliveryItemAsChecked extends OrgAction
{
    use WithProcurementEditAuthorisation;
    public function handle(StockDeliveryItem $stockDeliveryItem): StockDeliveryItem
    {
        return SetStockDeliveryItemCheckedQuantity::make()->action($stockDeliveryItem, [
            'unit_quantity_checked' => (float) $stockDeliveryItem->unit_quantity,
        ]);
    }

    public function asController(StockDeliveryItem $stockDeliveryItem, ActionRequest $request): void
    {
        $this->initialisation($stockDeliveryItem->organisation, $request);

        $this->handle($stockDeliveryItem);
    }

    public function action(StockDeliveryItem $stockDeliveryItem): StockDeliveryItem
    {
        $this->asAction = true;
        $this->initialisation($stockDeliveryItem->organisation, []);

        return $this->handle($stockDeliveryItem);
    }
}
