<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Apr 2026 10:27:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateWaitingItems;
use App\Actions\Dispatching\DeliveryNote\UpdateState\AutoFinishWaitingDeliveryNote;
use App\Actions\Ordering\Transaction\Traits\WithCalculateTransactionDiscount;
use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class PickAllItemFromWaitingWarehouse extends OrgAction
{
    use WithCalculateTransactionDiscount;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNoteItem $deliveryNoteItem, User $user, array $modelData): ?Picking
    {
        return DB::transaction(function () use ($deliveryNoteItem, $user, $modelData) {
            $locationOrgStock = LocationOrgStock::find(Arr::get($modelData, 'location_org_stock_id'));

            $availableInLocation = (float) ($locationOrgStock?->quantity ?? 0);
            $quantityToPick      = (float) $deliveryNoteItem->quantity_waiting_warehouse;

            if ($availableInLocation < $quantityToPick) {
                throw ValidationException::withMessages([
                    'location_org_stock_id' => __('Not enough stock in this location: :available available, :required required.', [
                        'available' => $availableInLocation,
                        'required'  => $quantityToPick,
                    ]),
                ]);
            }

            $deliveryNoteItem->update([
                'quantity_waiting_warehouse' => 0,
                'has_waiting_warehouse'      => false,
            ]);
            DeliveryNoteHydrateWaitingItems::run($deliveryNoteItem->delivery_note_id);

            $picking = PickAllItem::run(
                $deliveryNoteItem,
                [
                    'location_org_stock_id' => Arr::get($modelData, 'location_org_stock_id'),
                    'picker_user_id'        => $user->id,
                ]
            );


            AutoFinishWaitingDeliveryNote::run($deliveryNoteItem->deliveryNote);

            // To fix concurrent issue, discounts aren't applied after picking up from Waiting (reported by Erika)
            $this->calculateTransactionDiscountTotal($deliveryNoteItem->transaction);

            return $picking;
        });
    }

    public function rules(): array
    {
        return [
            'location_org_stock_id' => [
                'required',
                Rule::Exists('location_org_stocks', 'id')->where('warehouse_id', $this->warehouse->id)
            ]
        ];
    }


    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): void
    {
        $this->initialisationFromWarehouse($deliveryNoteItem->deliveryNote->warehouse, $request);

        $this->handle($deliveryNoteItem, $request->user(), $this->validatedData);
    }


}
