<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 19 Dec 2024 Malaysia Time
 * Copyright (c) 2024
 */

namespace App\Actions\GoodsIn\Sowing;

use App\Actions\GoodsIn\ReturnDeliveryNoteItem\CalculateReturnDeliveryNoteItemTotalSowed;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\OrgAction;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\GoodsIn\Sowing;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

class DeleteSowing extends OrgAction
{
    public function handle(Sowing $sowing, ?User $user = null): bool
    {
        $sowing->delete();
        $sowing->refresh();

        if ($sowing->orgStockMovement) {
            $location           = $sowing->orgStockMovement->location;
            $orgStock           = $sowing->orgStockMovement->orgStock;

            StoreOrgStockMovement::run(
                $orgStock,
                $location,
                [
                    'quantity' => -$sowing->quantity,
                    'type'     => OrgStockMovementTypeEnum::CANCEL_RETURN_PICKED,
                    'user_id'  => $user?->id,
                ],
                $sowing
            );
        }

        if ($sowing->returnItem) {
            CalculateReturnDeliveryNoteItemTotalSowed::make()->action($sowing->returnItem);

        }

        return true;
    }

    public function asController(Sowing $sowing, ActionRequest $request): void
    {
        $this->initialisationFromShop($sowing->shop, $request);

        $this->handle($sowing, $request->user());
    }

    public function action(Sowing $sowing, ?User $user = null): bool
    {
        $this->initialisationFromShop($sowing->shop, []);

        return $this->handle($sowing, $user);
    }
}
