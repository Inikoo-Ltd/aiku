<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\Packing\StorePacking;
use App\Actions\Ordering\Order\UpdateOrderStateToPacked;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShopTypeDeliveryNotes;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

class SetDeliveryNoteStateAsPacked extends OrgAction
{
    use WithActionUpdate;

    private DeliveryNote $deliveryNote;
    protected User $user;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        data_set($modelData, 'packed_at', now());
        data_set($modelData, 'packer_user_id', $this->user->id);
        data_set($modelData, 'state', DeliveryNoteStateEnum::PACKED->value);

        foreach ($deliveryNote->deliveryNoteItems->filter(fn ($item) => $item->packings->isEmpty()) as $item) {
            StorePacking::make()->action($item, $this->user, []);
        }
        $defaultParcel = [
            [
                'weight' => 1,
                'dimensions' => [5, 5, 5]
            ]
        ];

        data_set($modelData, 'parcels', $defaultParcel);

        UpdateOrderStateToPacked::make()->action($deliveryNote->orders->first());

        $deliveryNote = $this->update($deliveryNote, $modelData);

        OrganisationHydrateShopTypeDeliveryNotes::dispatch($deliveryNote->organisation, $deliveryNote->shop->type)
            ->delay($this->hydratorsDelay);

        return $deliveryNote;
    }


    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->user = $request->user();
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(DeliveryNote $deliveryNote, User $user): DeliveryNote
    {
        $this->user = $user;
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }
}
