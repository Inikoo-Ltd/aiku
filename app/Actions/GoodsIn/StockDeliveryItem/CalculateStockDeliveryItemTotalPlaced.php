<?php

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDeliveryItem;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateStockDeliveryItemTotalPlaced
{
    use AsAction;
    use WithActionUpdate;

    public function handle(StockDeliveryItem $stockDeliveryItem): StockDeliveryItem
    {
        if ($stockDeliveryItem->state === StockDeliveryItemStateEnum::CANCELLED) {
            return $stockDeliveryItem;
        }

        $placed    = (float) $stockDeliveryItem->sowings()->where('type', SowingTypeEnum::SOW)->sum('quantity');
        $checked   = (float) $stockDeliveryItem->unit_quantity_checked;
        $isChecked = $stockDeliveryItem->checked_at !== null || $checked > 0;

        $state = match (true) {
            $isChecked && $checked <= 0         => StockDeliveryItemStateEnum::NOT_RECEIVED,
            $checked > 0 && $placed >= $checked => StockDeliveryItemStateEnum::PLACED,
            $isChecked                          => StockDeliveryItemStateEnum::CHECKED,
            default                             => StockDeliveryItemStateEnum::RECEIVED,
        };

        return $this->update($stockDeliveryItem, [
            'unit_quantity_placed' => $placed,
            'state'                => $state,
            'checked_at'           => $isChecked ? ($stockDeliveryItem->checked_at ?? now()) : null,
            'not_received_at'      => $state === StockDeliveryItemStateEnum::NOT_RECEIVED ? ($stockDeliveryItem->not_received_at ?? now()) : null,
            'placed_at'            => $state === StockDeliveryItemStateEnum::PLACED ? ($stockDeliveryItem->placed_at ?? now()) : null,
        ]);
    }

    public function action(StockDeliveryItem $stockDeliveryItem): StockDeliveryItem
    {
        return $this->handle($stockDeliveryItem);
    }
}
