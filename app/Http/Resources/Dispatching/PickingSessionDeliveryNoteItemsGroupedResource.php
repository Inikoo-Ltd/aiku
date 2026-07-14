<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Jun 2025 14:00:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsStateHandling;
use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Enums\Dispatching\DeliveryNoteLeaflet\DeliveryNoteLeafletStateEnum;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Billables\Packaging;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteLeaflet;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $delivery_note_id
 * @property mixed $delivery_note_slug
 * @property mixed $delivery_note_customer_notes
 * @property mixed $delivery_note_public_notes
 * @property mixed $delivery_note_internal_notes
 * @property mixed $delivery_note_shipping_notes
 * @property mixed $delivery_note_reference
 * @property mixed $packed_in
 * @property mixed $delivery_note_is_premium_dispatch
 * @property mixed $delivery_note_has_extra_packing
 */
class PickingSessionDeliveryNoteItemsGroupedResource extends JsonResource
{
    public function toArray($request): array
    {
        $deliveryNote = DeliveryNote::find($this->delivery_note_id);
        $packaging    = $this->effectivePackaging($deliveryNote);

        return [
            'id'                              => $this->delivery_note_id,
            'delivery_note_reference'         => $this->delivery_note_reference,
            'delivery_note_state_icon'        => $deliveryNote->state->stateIcon()[$deliveryNote->state->value],
            'delivery_note_slug'              => $this->delivery_note_slug,
            'delivery_note_id'                => $this->delivery_note_id,
            'delivery_note_state'             => $deliveryNote->state,
            'delivery_note_is_for_collection' => (bool)$deliveryNote->collection_address_id,

            'delivery_note_customer_notes' => $this->delivery_note_customer_notes,
            'delivery_note_public_notes'   => $this->delivery_note_public_notes,
            'delivery_note_internal_notes' => $this->delivery_note_internal_notes,
            'delivery_note_shipping_notes' => $this->delivery_note_shipping_notes,

            'delivery_note_is_premium_dispatch' => $this->delivery_note_is_premium_dispatch,
            'delivery_note_has_extra_packing'   => $this->delivery_note_has_extra_packing,

            'packaging'         => $this->getPackaging($packaging),
            'packaging_options' => $this->getPackagingOptions($deliveryNote, $packaging?->family_code),
            'leaflets'          => $this->getLeaflets($deliveryNote),
            'print_status'      => $this->getPrintStatus($deliveryNote),

            'items' => DeliveryNoteItemsStateHandlingResource::collection(IndexDeliveryNoteItemsStateHandling::run($deliveryNote, ignoreParentPagination: true))->resolve()
        ];
    }

    /**
     * The effective packaging for the delivery note: its own, or (when not yet copied)
     * the packaging chosen on its order.
     */
    private function effectivePackaging(DeliveryNote $deliveryNote): ?Packaging
    {
        return $deliveryNote->packaging ?? $deliveryNote->orders()->first()?->packaging;
    }

    /** @return array{id: int, name: string, dimensions: string|null}|null */
    private function getPackaging(?Packaging $packaging): ?array
    {
        if (!$packaging) {
            return null;
        }

        return [
            'id'         => $packaging->id,
            'name'       => $packaging->name,
            'dimensions' => $this->dimensions($packaging),
        ];
    }

    /** @return array<int, array{id: int, name: string, dimensions: string|null, price: float, is_free: bool, family_code: string|null, image: mixed}> */
    private function getPackagingOptions(DeliveryNote $deliveryNote, ?string $familyCode): array
    {
        // The customer already paid for a specific packaging family, so the warehouse may
        // only swap to another size within that exact same family.
        if (!$familyCode) {
            return [];
        }

        return Packaging::where('shop_id', $deliveryNote->shop_id)
            ->where('state', PackagingStateEnum::ACTIVE)
            ->where('family_code', $familyCode)
            ->with('image')
            ->orderBy('position')
            ->orderBy('price')
            ->get()
            ->map(fn (Packaging $packaging) => [
                'id'          => $packaging->id,
                'name'        => $packaging->name,
                'dimensions'  => $this->dimensions($packaging),
                'price'       => (float) $packaging->price,
                'is_free'     => (float) $packaging->price === 0.0,
                'family_code' => $packaging->family_code,
                'image'       => $packaging->image ? ImageResource::make($packaging->image)->resolve() : null,
            ])->all();
    }

    /** @return array<int, array{id: int, name: string, type: string, copies: int, state: string, state_label: string, has_media: bool}> */
    private function getLeaflets(DeliveryNote $deliveryNote): array
    {
        return $deliveryNote->leaflets
            ->map(fn (DeliveryNoteLeaflet $leaflet) => [
                'id'          => $leaflet->id,
                'name'        => $leaflet->name,
                'type'        => $leaflet->type->value,
                'copies'      => $leaflet->copies,
                'state'       => $leaflet->state->value,
                'state_label' => $leaflet->state->labels()[$leaflet->state->value],
                'has_media'   => $leaflet->media_id !== null,
            ])->values()->all();
    }

    /** @return array{total: int, printed: int, all_printed: bool, label: string} */
    private function getPrintStatus(DeliveryNote $deliveryNote): array
    {
        $leaflets = $deliveryNote->leaflets;
        $total    = $leaflets->count();
        $printed  = $leaflets->whereIn('state', [
            DeliveryNoteLeafletStateEnum::PRINTED,
            DeliveryNoteLeafletStateEnum::INCLUDED,
        ])->count();

        return [
            'total'       => $total,
            'printed'     => $printed,
            'all_printed' => $total > 0 && $printed === $total,
            'label'       => $total === 0
                ? __('No inserts')
                : ($printed === $total ? __('Printed') : __('Not printed')),
        ];
    }

    private function dimensions(Packaging $packaging): ?string
    {
        if (!$packaging->width || !$packaging->height || !$packaging->depth) {
            return null;
        }

        return "{$packaging->width}x{$packaging->height}+{$packaging->depth}mm";
    }
}
