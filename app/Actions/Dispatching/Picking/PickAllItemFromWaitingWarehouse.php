<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Apr 2026 10:27:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNote\UpdateState\AutoFinishWaitingDeliveryNote;
use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class PickAllItemFromWaitingWarehouse extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNoteItem $deliveryNoteItem, User $user, array $modelData): ?Picking
    {
        return DB::transaction(function () use ($deliveryNoteItem, $user, $modelData) {
            $deliveryNoteItem->update([
                'quantity_waiting_warehouse' => 0,
                'has_waiting_warehouse'      => false,
            ]);

            $picking= PickAllItem::make()->action(
                $deliveryNoteItem,
                [
                    'location_org_stock_id' => Arr::get($modelData, 'location_org_stock_id'),
                    'picker_user_id'        => $user->id,
                ]
            );
            AutoFinishWaitingDeliveryNote::run($deliveryNoteItem->deliveryNote);
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
