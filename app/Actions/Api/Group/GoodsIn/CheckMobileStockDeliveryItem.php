<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemCheckedQuantity;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class CheckMobileStockDeliveryItem
{
    use AsController;
    use WithMobileStockDeliveries;

    public function asController(Warehouse $warehouse, StockDelivery $stockDelivery, StockDeliveryItem $stockDeliveryItem, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanBookIn($user, $warehouse, $stockDelivery);
        $this->ensureItemOfStockDelivery($stockDelivery, $stockDeliveryItem);

        $data = Validator::make($request->all(), [
            'sko_quantity' => ['required', 'numeric', 'gte:0', 'max:1000000'],
        ])->validate();

        if (!$stockDeliveryItem->canBeReceivedAfterAll()) {
            $this->ensureItemNotCancelled($stockDeliveryItem);
            if (!$this->canBeChecked($stockDelivery)) {
                $this->refuseState(__('Items can only be checked while the delivery is being booked in'));
            }
        }

        $requested   = (float) $data['sko_quantity'];
        $unitsPerSko = $stockDeliveryItem->unitsPerSko();
        $before      = [(float) $stockDeliveryItem->unit_quantity_checked, $stockDeliveryItem->state];

        $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDeliveryItem, ['sko_quantity_checked' => $requested]);

        $checked  = (float) $stockDeliveryItem->unit_quantity_checked;
        $packedIn = (int) $stockDeliveryItem->orgStock?->packed_in;
        $changed  = $before !== [$checked, $stockDeliveryItem->state];
        $skos     = $this->skoLabel(round($checked / $unitsPerSko, 4), $packedIn);

        $message = match (true) {
            round($requested * $unitsPerSko, 4) < $checked => __(':placed already placed, so the checked quantity stays at :placed.', ['placed' => $skos]),
            !$changed                                      => __('Already checked as :quantity.', ['quantity' => $skos]),
            $checked <= 0                                  => __('Marked as not received.'),
            default                                        => __('Checked :quantity.', ['quantity' => $skos]),
        };

        return $this->respondWithStockDelivery($warehouse, $stockDelivery, $user, $changed ? 'done' : 'nothing_changed', $message);
    }
}
