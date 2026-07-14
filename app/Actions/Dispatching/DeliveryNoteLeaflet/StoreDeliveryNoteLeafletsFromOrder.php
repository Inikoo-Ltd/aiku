<?php

/*
 * Author: Andi Ferdiawan
 * Created: Mon, 14 Jul 2026 10:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Dispatching\DeliveryNoteLeaflet;

use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
use App\Enums\Dispatching\DeliveryNoteLeaflet\DeliveryNoteLeafletStateEnum;
use App\Models\Billables\Leaflet;
use App\Models\Billables\ModelHasLeaflet;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteLeaflet;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsAction;


class StoreDeliveryNoteLeafletsFromOrder
{
    use AsAction;

    public function handle(DeliveryNote $deliveryNote, Order $order): void
    {
        $familyCode = $order->packaging?->family_code;

        foreach ($order->insert_types ?? [] as $leafletId) {
            $leaflet = Leaflet::find($leafletId);
            if (!$leaflet) {
                continue;
            }

            $modelHasLeaflet = $this->findCustomerLeaflet($order, (int) $leafletId, $familyCode);

            $this->createLeaflet($deliveryNote, [
                'model_has_leaflet_id' => $modelHasLeaflet?->id,
                'type'                 => $leaflet->type,
                'name'                 => $leaflet->name,
                'media_id'             => $modelHasLeaflet?->media_id,
            ]);
        }

        if (filled($order->personalised_message)) {
            $this->createLeaflet($deliveryNote, [
                'type'    => LeafletTypeEnum::PERSONALISED_MESSAGE,
                'name'    => __('Personalised Message'),
                'message' => $order->personalised_message,
            ]);
        }
    }

    private function findCustomerLeaflet(Order $order, int $leafletId, ?string $familyCode): ?ModelHasLeaflet
    {
        $query = ModelHasLeaflet::where('model_type', 'Customer')
            ->where('model_id', $order->customer_id)
            ->where('shop_id', $order->shop_id)
            ->where('leaflet_id', $leafletId)
            ->when($familyCode, fn ($q) => $q->whereHas('packaging', fn ($p) => $p->where('family_code', $familyCode)));

        return (clone $query)->whereNotNull('media_id')->first() ?? $query->first();
    }

    private function createLeaflet(DeliveryNote $deliveryNote, array $modelData): DeliveryNoteLeaflet
    {
        return DeliveryNoteLeaflet::create(array_merge(
            [
                'group_id'         => $deliveryNote->group_id,
                'organisation_id'  => $deliveryNote->organisation_id,
                'shop_id'          => $deliveryNote->shop_id,
                'delivery_note_id' => $deliveryNote->id,
                'copies'           => 1,
                'state'            => DeliveryNoteLeafletStateEnum::PENDING_PRINT,
            ],
            $modelData
        ));
    }
}
