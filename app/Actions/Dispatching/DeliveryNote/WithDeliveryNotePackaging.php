<?php

/*
 * Author: Andi Ferdiawan
 * Created: Thu, 16 Jul 2026 10:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Billables\Packaging;
use App\Models\Dispatching\DeliveryNote;

trait WithDeliveryNotePackaging
{
    /**
     * The effective packaging for the delivery note: its own, or (when not yet copied)
     * the packaging chosen on its order.
     */
    protected function effectivePackaging(DeliveryNote $deliveryNote): ?Packaging
    {
        return $deliveryNote->packaging ?? $deliveryNote->orders()->first()?->packaging;
    }

    /** @return array{id: int, name: string, dimensions: string|null}|null */
    protected function getPackaging(?Packaging $packaging): ?array
    {
        if (!$packaging) {
            return null;
        }

        return [
            'id'         => $packaging->id,
            'name'       => $packaging->name,
            'dimensions' => $this->packagingDimensions($packaging),
        ];
    }

    /** @return array<int, array{id: int, name: string, dimensions: string|null, price: float, is_free: bool, family_code: string|null, image: mixed}> */
    protected function getPackagingOptions(DeliveryNote $deliveryNote, ?string $familyCode): array
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
                'dimensions'  => $this->packagingDimensions($packaging),
                'price'       => (float) $packaging->price,
                'is_free'     => (float) $packaging->price === 0.0,
                'family_code' => $packaging->family_code,
                'image'       => $packaging->image ? ImageResource::make($packaging->image)->resolve() : null,
            ])->all();
    }

    protected function packagingDimensions(Packaging $packaging): ?string
    {
        if (!$packaging->width || !$packaging->height || !$packaging->depth) {
            return null;
        }

        return "{$packaging->width}x{$packaging->height}+{$packaging->depth}mm";
    }
}
