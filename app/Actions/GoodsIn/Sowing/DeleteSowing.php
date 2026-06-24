<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 19 Dec 2024 Malaysia Time
 * Copyright (c) 2024
 */

namespace App\Actions\GoodsIn\Sowing;

use App\Actions\GoodsIn\ReturnDeliveryNoteItem\CalculateReturnDeliveryNoteItemTotalSowed;
use App\Actions\Inventory\OrgStockMovement\DeleteOrgStockMovement;
use App\Actions\OrgAction;
use App\Models\GoodsIn\Sowing;
use Lorisleiva\Actions\ActionRequest;

class DeleteSowing extends OrgAction
{
    public function handle(Sowing $sowing): bool
    {
        $sowing->delete();
        $sowing->refresh();

        if ($sowing->orgStockMovement) {
            DeleteOrgStockMovement::run($sowing->orgStockMovement);
        }

        if ($sowing->returnItem) {
            CalculateReturnDeliveryNoteItemTotalSowed::make()->action($sowing->returnItem);

        }


        return true;
    }

    public function asController(Sowing $sowing, ActionRequest $request): void
    {
        $this->initialisationFromShop($sowing->shop, $request);

        $this->handle($sowing);
    }

    public function action(Sowing $sowing): bool
    {
        $this->initialisationFromShop($sowing->shop, []);

        return $this->handle($sowing);
    }
}
