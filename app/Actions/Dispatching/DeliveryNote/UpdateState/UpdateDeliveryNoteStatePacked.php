<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Feb 2026 11:40:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateTrolleys;
use App\Actions\Dispatching\Shipment\StoreShipmentFromFaire;
use App\Actions\Dispatching\Packing\StorePacking;
use App\Actions\Dispatching\PickingSession\AutoFinishPackingPickingSession;
use App\Actions\Dropshipping\Tiktok\Order\ProcessTiktokOrderShipment;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToPacked;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteStatePacked extends OrgAction
{
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;

    private DeliveryNote $deliveryNote;
    protected User $user;


    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        $oldState = $deliveryNote->state;

        data_set($modelData, 'packed_at', now());
        data_set($modelData, 'packer_user_id', $this->user->id);
        data_set($modelData, 'state', DeliveryNoteStateEnum::PACKED->value);


        $deliveryNote = DB::transaction(function () use ($deliveryNote, $modelData) {
            foreach ($deliveryNote->deliveryNoteItems->filter(fn ($item) => $item->packings->isEmpty()) as $item) {
                StorePacking::make()->action($item, $this->user, []);
            }

            if (count($deliveryNote->parcels) == 0) {
                $defaultParcel = [
                    [
                        'weight'     => $deliveryNote->effective_weight / 1000,
                        'dimensions' => [5, 5, 5]
                    ]
                ];

                data_set($modelData, 'parcels', $defaultParcel);
            }

            if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
                UpdateOrderStateToPacked::make()->action($deliveryNote->orders->first(), $deliveryNote);
            }

            $deliveryNote = $this->update($deliveryNote, $modelData);

            if ($deliveryNote->pickingSessions) {
                foreach ($deliveryNote->pickingSessions as $pickingSession) {
                    AutoFinishPackingPickingSession::run($pickingSession);
                }
            }

            foreach ($deliveryNote->trolleys as $trolley) {
                DB::table('delivery_note_has_trolleys')
                    ->where('delivery_note_id', $deliveryNote->id)->where('trolley_id', $trolley->id)->delete();
                $trolley->update(['current_delivery_note_id' => null]);
            }

            $order = $deliveryNote->orders->first();

            if ($deliveryNote->is_shipping_by_external) {
                if ($deliveryNote->shop->engine == ShopEngineEnum::FAIRE) {
                    StoreShipmentFromFaire::run($deliveryNote);
                } elseif ($order->platform->type == PlatformTypeEnum::TIKTOK) {
                    ProcessTiktokOrderShipment::run($order);
                }
            }

            return $deliveryNote;
        });

        $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
        $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::PACKED);
        DeliveryNoteHydrateTrolleys::dispatch($deliveryNote->id);
        return $deliveryNote;
    }


    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->user         = $request->user();
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }


    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote, User $user): DeliveryNote
    {
        $this->user         = $user;
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }
}
