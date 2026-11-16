<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class NotReturnMobileReturnItem
{
    use AsController;
    use WithMobileReturnItemPuts;

    /**
     * @throws \Throwable
     */
    public function asController(Warehouse $warehouse, ReturnDeliveryNote $returnDeliveryNote, ReturnDeliveryNoteItem $returnDeliveryNoteItem, ActionRequest $request): JsonResponse
    {
        return $this->putFromRequest($warehouse, $returnDeliveryNote, $returnDeliveryNoteItem, $request, SowingTypeEnum::NOT_SOW);
    }
}
