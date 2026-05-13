<?php

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateTrolleys;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Dispatching\PickingSession\AutoFinishPackingPickingSession;
use App\Actions\Dispatching\Shipment\StoreShipmentFromFaire;
use App\Actions\Dropshipping\Tiktok\Order\ProcessTiktokOrderShipment;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToPacked;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Facades\DB;

class CheckAndCompleteDeliveryNotePacking extends OrgAction
{
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;

    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        $oldState = $deliveryNote->state;
        $siblingDeliveryNoteItems = $deliveryNote->deliveryNoteItems()->with('packings')->get();
        // Ignore deliveryNoteItem with quantity_not_picked
        $hasUnfinishedPackings = $siblingDeliveryNoteItems->filter(fn ($item) => empty((float)$item->quantity_not_picked) && $item->packings->count() == 0);

        if ($hasUnfinishedPackings->count() == 0) {

            foreach ($deliveryNote->trolleys as $trolley) {
                DB::table('delivery_note_has_trolleys')
                    ->where('delivery_note_id', $deliveryNote->id)->where('trolley_id', $trolley->id)->delete();
                $trolley->update(['current_delivery_note_id' => null]);
            }

            $defaultParcel = count($deliveryNote->parcels) == 0 ? [
                [
                    'weight'     => $deliveryNote->effective_weight / 1000,
                    'dimensions' => [5, 5, 5]
                ]
            ] : $deliveryNote->parcels;

            UpdateDeliveryNote::make()->action($deliveryNote, [
                'packed_at'      => now(),
                'packer_user_id' => request()->user()?->id,
                'state'          => DeliveryNoteStateEnum::PACKED->value,
                'parcels'        => $defaultParcel
            ]);

            DeliveryNoteHydrateTrolleys::dispatch($deliveryNote->id);

            if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
                UpdateOrderStateToPacked::make()->action($deliveryNote->orders->first(), $deliveryNote);
            }

            if ($deliveryNote->pickingSessions) {
                foreach ($deliveryNote->pickingSessions as $pickingSession) {
                    AutoFinishPackingPickingSession::run($pickingSession);
                }
            }

            $order = $deliveryNote->orders->first();

            if ($deliveryNote->is_shipping_by_external && $deliveryNote->shipments->isEmpty()) {
                if ($deliveryNote->shop->engine == ShopEngineEnum::FAIRE) {
                    StoreShipmentFromFaire::run($deliveryNote);
                } elseif ($order->platform->type == PlatformTypeEnum::TIKTOK) {
                    ProcessTiktokOrderShipment::run($order);
                }
            }

            $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
            $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::PACKED);
        }

        $deliveryNote->refresh();

        return $deliveryNote;
    }
}
