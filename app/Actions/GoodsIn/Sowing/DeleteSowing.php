<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 19 Dec 2024 Malaysia Time
 * Copyright (c) 2024
 */

namespace App\Actions\GoodsIn\Sowing;

use App\Actions\Inventory\OrgStockMovement\DeleteOrgStockMovement;
use App\Actions\OrgAction;
use App\Models\GoodsIn\Sowing;
use Lorisleiva\Actions\ActionRequest;

class DeleteSowing extends OrgAction
{
    public function handle(Sowing $sowing): bool
    {
        $orgStockMovement = $sowing->orgStockMovement;

        $sowing->delete();

        if ($orgStockMovement) {
            DeleteOrgStockMovement::run($orgStockMovement);
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
