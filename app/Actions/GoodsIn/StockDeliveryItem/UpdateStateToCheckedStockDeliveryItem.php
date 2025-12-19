<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 10 May 2023 14:06:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\GoodsIn\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDeliveryItem;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateStateToCheckedStockDeliveryItem
{
    use WithActionUpdate;
    use AsAction;
    use HasStockDeliveryHydrators;

    public function handle(StockDeliveryItem $stockDeliveryItem, $modelData): StockDeliveryItem
    {
        $data = [
            'state' => StockDeliveryItemStateEnum::CHECKED,
        ];
        $data['checked_at']            = now();
        $data['unit_quantity_checked'] = $modelData['unit_quantity_checked'];

        $stockDeliveryItem = $this->update($stockDeliveryItem, $data);

        $this->runStockDeliveryHydrators($stockDeliveryItem->stockDelivery);

        return $stockDeliveryItem;
    }

    public function rules(): array
    {
        return [
            'unit_quantity_checked' => ['required', 'numeric']
        ];
    }

    public function action(StockDeliveryItem $stockDeliveryItem, $modelData): StockDeliveryItem
    {
        $this->setRawAttributes($modelData);
        $validatedData = $this->validateAttributes();

        return $this->handle($stockDeliveryItem, $validatedData);
    }

    public function asController(StockDeliveryItem $stockDeliveryItem, ActionRequest $request): StockDeliveryItem
    {
        $request->validate();

        return $this->handle($stockDeliveryItem, $request->all());
    }
}
