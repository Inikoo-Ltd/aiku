<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-14h-39m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\DeliveryNoteBoxPackingList;
use App\Actions\Dispatching\DeliveryNote\GetDeliveryNoteConsumables;
use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\WithDeliveryNoteLeaflets;
use App\Actions\Dispatching\DeliveryNote\WithDeliveryNotePackaging;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\Shipment\ShipmentLabelTypeEnum;
use App\Http\Resources\Dispatching\ShipmentsResource;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;

trait WithMobilePackingFields
{
    use WithDeliveryNoteLeaflets;
    use WithDeliveryNotePackaging;
    use WithMobilePrinters;

    /**
     * @return array<int, array{code: string, quantity: float}>
     */
    private function consumables(DeliveryNote $deliveryNote): array
    {
        return in_array($deliveryNote->state, [DeliveryNoteStateEnum::PACKING, DeliveryNoteStateEnum::PACKED, DeliveryNoteStateEnum::FINALISED, DeliveryNoteStateEnum::DISPATCHED], true)
            ? GetDeliveryNoteConsumables::run($deliveryNote)
            : [];
    }

    /**
     * @return array{current: array{id: int, name: string}|null}|null
     */
    private function packaging(DeliveryNote $deliveryNote): ?array
    {
        if (!$deliveryNote->shop->hasPackagingAndInserts()) {
            return null;
        }

        $packaging = $this->getPackaging($this->effectivePackaging($deliveryNote));

        return ['current' => $packaging ? Arr::only($packaging, ['id', 'name']) : null];
    }

    /**
     * @return array{leaflets: array<int, array<string, mixed>>, total: int, printed: int, all_printed: bool, label: string, can_print_all: bool}|null
     */
    private function inserts(DeliveryNote $deliveryNote, User $user): ?array
    {
        if (!$deliveryNote->shop->hasPackagingAndInserts()) {
            return null;
        }

        return [
            'leaflets'      => collect($this->getLeaflets($deliveryNote))
                ->map(fn (array $leaflet) => [
                    'id'           => $leaflet['id'],
                    'name'         => $leaflet['name'],
                    'type'         => $leaflet['type'],
                    'copies'       => (int) $leaflet['copies'],
                    'state'        => $leaflet['state'],
                    'state_label'  => $leaflet['state_label'],
                    'can_open_pdf' => (bool) $leaflet['has_media'],
                ])
                ->values()
                ->all(),
            ...$this->getPrintStatus($deliveryNote),
            'can_print_all' => $this->canPrintAllInserts($deliveryNote, $user),
        ];
    }

    /**
     * @return array{required: bool, number_boxes: int, missing_message: string|null, pdf_url: string}|null
     */
    private function boxPackingList(DeliveryNote $deliveryNote): ?array
    {
        $boxPackingList = DeliveryNoteBoxPackingList::make();

        if (!$boxPackingList->isRequired($deliveryNote)) {
            return null;
        }

        return [
            'required'        => true,
            'number_boxes'    => $boxPackingList->numberBoxes($deliveryNote),
            'missing_message' => $boxPackingList->missingBoxesMessage($deliveryNote),
            'pdf_url'         => route('grp.api.mobile.delivery_notes.packing_list_pdf', $deliveryNote->id),
        ];
    }

    private function parcelsEditable(DeliveryNote $deliveryNote, bool $isEditable): bool
    {
        return $isEditable
            && in_array($deliveryNote->state, [DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::PACKING, DeliveryNoteStateEnum::PACKED], true)
            && $deliveryNote->shipments()->doesntExist();
    }

    /**
     * @param array<int, array{key: string, label: string, disabled_reason?: string}> $availableActions
     * @return array<string, mixed>
     */
    private function shipping(DeliveryNote $deliveryNote, User $user, array $availableActions): array
    {
        $showDeliveryNote = ShowDeliveryNote::make();
        $engineLabel      = $this->externalEngineLabel($deliveryNote);

        return [
            'can_add'           => collect($availableActions)->contains('key', 'add_shipment'),
            'can_print_labels'  => $this->canPrintLabels($user),
            ...$showDeliveryNote->getShipperDirective($deliveryNote),
            'can_override_lock' => $showDeliveryNote->canOverrideShipperLock($deliveryNote),
            'external'          => $deliveryNote->is_shipping_by_external ? ['engine_label' => $engineLabel] : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function shipments(DeliveryNote $deliveryNote, User $user): array
    {
        $canDelete = !$deliveryNote->isLockedInAurora()
            && !in_array($deliveryNote->state, [DeliveryNoteStateEnum::DISPATCHED, DeliveryNoteStateEnum::CANCELLED], true)
            && $deliveryNote->canBeShippedBy($user);

        $shipments = $deliveryNote->shipments()->with('shipper')->get();
        $rows      = ShipmentsResource::collection($shipments)->resolve();

        return $shipments
            ->values()
            ->map(fn (Shipment $shipment, int $position) => [
                'id'            => $shipment->id,
                'shipper'       => $rows[$position]['name'],
                'shipper_code'  => $rows[$position]['shipper_code'],
                'tracking'      => $rows[$position]['tracking'],
                'trackings'     => array_values((array) $rows[$position]['trackings']),
                'tracking_urls' => array_values((array) $rows[$position]['tracking_urls']),
                'is_printable'  => (bool) $rows[$position]['is_printable'],
                'label'         => $this->shipmentLabel($deliveryNote, $shipment),
                'can_delete'    => $canDelete,
            ])
            ->all();
    }

    /**
     * @return array{kind: string, url: string|null}
     */
    private function shipmentLabel(DeliveryNote $deliveryNote, Shipment $shipment): array
    {
        if (static::hasPdfLabel($shipment)) {
            return ['kind' => 'download', 'url' => route('grp.api.mobile.delivery_notes.shipments.label', [$deliveryNote->id, $shipment->id])];
        }

        if ($shipment->combined_label_url) {
            return ['kind' => 'external', 'url' => $shipment->combined_label_url];
        }

        return ['kind' => 'none', 'url' => null];
    }

    public static function hasPdfLabel(Shipment $shipment): bool
    {
        return filled($shipment->label) && $shipment->label_type === ShipmentLabelTypeEnum::PDF;
    }

    /**
     * @return array{delivery_note_pdf_url: string, gift_message_pdf_url: string|null}
     */
    private function documents(DeliveryNote $deliveryNote, bool $hasGiftMessage): array
    {
        return [
            'delivery_note_pdf_url' => route('grp.api.mobile.delivery_notes.pdf', $deliveryNote->id),
            'gift_message_pdf_url'  => $hasGiftMessage ? route('grp.api.mobile.delivery_notes.gift_message_pdf', $deliveryNote->id) : null,
        ];
    }
}
