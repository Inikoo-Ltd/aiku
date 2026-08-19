<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Apr 2026 10:44:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Dispatching\Picking;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class DeletePicking extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Picking $picking, ?User $user): bool
    {
        $deliveryNoteItem = $picking->deliveryNoteItem;

        $orgStockMovement = $picking->orgStockMovement;

        $deliveryNoteItem = DB::transaction(function () use ($picking, $orgStockMovement, $deliveryNoteItem, $user) {
            $quantity = $picking->quantity;

            if ($orgStockMovement) {
                $location           = $orgStockMovement->location;
                $orgStock           = $orgStockMovement->orgStock;

                StoreOrgStockMovement::run(
                    $orgStock,
                    $location,
                    [
                        'quantity' => abs($picking->quantity),
                        'type'     => OrgStockMovementTypeEnum::CANCEL_PICKED,
                        'user_id'  => $user?->id,
                    ],
                    $picking
                );

                if (app()->environment('production')) {
                    DeletePickingInAurora::dispatch(
                        $picking->id,
                        $picking->organisation,
                        $picking->picker->contact_name,
                        $picking->orgStock
                    );
                }
            }

            $picking->delete();

            /**
             * Recalculated before re-parking: the waiting clamp reads quantity_picked, which still
             * counts the picking just deleted, so a line partly parked with CRM comes out with
             * nothing outstanding and the abort kills the caller - the Faire resync died this way
             * on a line the buyer had removed. With fresh totals the freed quantity is what goes
             * back to waiting, and when the rest of the line is with CRM there is nothing to park.
             */
            CalculateDeliveryNoteItemTotalPicked::make()->action($deliveryNoteItem->refresh());
            $deliveryNoteItem->refresh();

            $outstanding = (float)$deliveryNoteItem->quantity_required
                - (float)$deliveryNoteItem->quantity_picked
                - (float)$deliveryNoteItem->quantity_waiting_crm;

            if ($deliveryNoteItem->state == DeliveryNoteItemStateEnum::HANDLING_BLOCKED && $quantity > 0 && $outstanding > 0) {
                SetAsWaitingWarehouse::make()->action(
                    $deliveryNoteItem,
                    $user,
                    [
                        'quantity' => $deliveryNoteItem->quantity_waiting_warehouse + $quantity
                    ]
                );
            }

            return $deliveryNoteItem;
        });
        $deliveryNoteItem->refresh();


        CalculateDeliveryNoteItemTotalPicked::make()->action($deliveryNoteItem);

        return true;
    }

    /**
     * @throws \Throwable
     */
    public function asController(Picking $picking, ActionRequest $request): void
    {
        $this->initialisationFromShop($picking->shop, $request);

        $this->handle($picking, $request->user());
    }

    /**
     * @throws \Throwable
     */
    public function action(Picking $picking, ?User $user): bool
    {
        $this->initialisationFromShop($picking->shop, []);

        return $this->handle($picking, $user);
    }
}
