<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Apr 2026 10:04:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateWaitingItems;
use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class SetAsWaitingWarehouse extends OrgAction
{
    use WithActionUpdate;

    private DeliveryNoteItem $deliveryNoteItem;
    protected ?User $user = null;

    public function handle(DeliveryNoteItem $deliveryNoteItem, array $modelData): DeliveryNoteItem
    {
        /*
         * Faire owns its orders: we cannot alter what was bought, only follow what Faire sends.
         * Holding an item for the office to negotiate has no meaning there — the customer
         * changes the order in the Faire portal and the next sync brings it across — and it
         * left orders blocked with no way out. The allow_waiting setting is per organisation,
         * so it cannot tell a Faire shop from the b2b shop beside it.
         */
        if ($deliveryNoteItem->deliveryNote->shop->type == ShopTypeEnum::EXTERNAL) {
            abort(403, 'Waiting is not available on external shop orders, the order has to be changed in the marketplace');
        }

        // Disable waiting if setting is off
        if (!data_get($this->organisation->settings, 'orders.allow_waiting', false)) {
            abort(403, 'Waiting is not enabled for this organisation');
        }

        $dataToUpdate = [
            'state'                      => DeliveryNoteItemStateEnum::HANDLING_BLOCKED,
            'quantity_waiting_warehouse' => $modelData['quantity'],
            'has_waiting_warehouse'      => true,
        ];
        if (Arr::has($modelData, 'note')) {
            $dataToUpdate['notes'] = $modelData['note'];
        }
        $deliveryNoteItem->update(
            $dataToUpdate
        );
        DeliveryNoteHydrateWaitingItems::run($deliveryNoteItem->delivery_note_id);
        CalculateDeliveryNoteItemTotalPicked::run($deliveryNoteItem);

        return $deliveryNoteItem;
    }

    public function rules(): array
    {
        return [
            'note'     => ['sometimes', 'nullable', 'string'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }


    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): DeliveryNoteItem
    {
        $this->user             = $request->user();
        $this->deliveryNoteItem = $deliveryNoteItem;
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }

    public function action(DeliveryNoteItem $deliveryNoteItem, ?User $user, array $modelData): DeliveryNoteItem
    {
        $this->asAction         = true;
        $this->user             = $user;
        $this->deliveryNoteItem = $deliveryNoteItem;

        $this->initialisationFromShop($deliveryNoteItem->shop, $modelData);

        return $this->handle($deliveryNoteItem, $this->validatedData);
    }


}
