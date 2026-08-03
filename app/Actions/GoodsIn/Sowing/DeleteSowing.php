<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 19 Dec 2024 Malaysia Time
 * Copyright (c) 2024
 */

namespace App\Actions\GoodsIn\Sowing;

use App\Actions\GoodsIn\ReturnDeliveryNoteItem\CalculateReturnDeliveryNoteItemTotalSowed;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDelivery\UpdatePurchaseOrdersDeliveryStateFromStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryStateFromGoodsIn;
use App\Actions\GoodsIn\StockDeliveryItem\CalculateStockDeliveryItemTotalPlaced;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrderTransaction\UpdatePurchaseOrderTransactionDeliveryStateFromStockDeliveryItem;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\GoodsIn\Sowing;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

class DeleteSowing extends OrgAction
{
    public function handle(Sowing $sowing, ?User $user = null): bool
    {
        $stockDeliveryItem = $sowing->stockDeliveryItem;

        $sowing->delete();
        $sowing->refresh();

        if ($sowing->orgStockMovement) {
            $location           = $sowing->orgStockMovement->location;
            $orgStock           = $sowing->orgStockMovement->orgStock;

            $type = $sowing->stock_delivery_id ? OrgStockMovementTypeEnum::CANCEL_PURCHASE : OrgStockMovementTypeEnum::CANCEL_RETURN_PICKED;

            StoreOrgStockMovement::run(
                $orgStock,
                $location,
                [
                    'quantity' => -$sowing->quantity,
                    'type'     => $type,
                    'user_id'  => $user?->id,
                ],
                $sowing
            );
        }

        if ($sowing->returnItem) {
            CalculateReturnDeliveryNoteItemTotalSowed::make()->action($sowing->returnItem);
        }

        if ($stockDeliveryItem) {
            $stockDeliveryItem = CalculateStockDeliveryItemTotalPlaced::run($stockDeliveryItem);

            $stockDelivery = $stockDeliveryItem->stockDelivery;

            StockDeliveriesHydrateItems::run($stockDelivery);
            UpdatePurchaseOrderTransactionDeliveryStateFromStockDeliveryItem::run($stockDeliveryItem);
            UpdateStockDeliveryStateFromGoodsIn::run($stockDelivery);
            UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($stockDelivery->refresh());
        }

        return true;
    }

    public function asController(Sowing $sowing, ActionRequest $request): void
    {
        if ($sowing->stockDeliveryItem) {
            $this->initialisation($sowing->stockDeliveryItem->organisation, $request);
        } else {
            $this->initialisationFromShop($sowing->shop, $request);
        }

        $this->handle($sowing, $request->user());
    }

    public function action(Sowing $sowing, ?User $user = null): bool
    {
        if ($sowing->stockDeliveryItem) {
            $this->initialisation($sowing->stockDeliveryItem->organisation, []);
        } else {
            $this->initialisationFromShop($sowing->shop, []);
        }

        return $this->handle($sowing, $user);
    }
}
