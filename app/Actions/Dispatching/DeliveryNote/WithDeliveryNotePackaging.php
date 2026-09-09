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
        if (!$deliveryNote->shop?->hasPackagingAndInserts()) {
            return null;
        }

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


    protected function paidPackagingPrice(DeliveryNote $deliveryNote): ?float
    {
        $order = $deliveryNote->orders()->first();

        if (!$order) {
            return null;
        }

        $transaction = $order->transactions()->where('model_type', 'Packaging')->first();

        if ($transaction && (float) $transaction->quantity_ordered > 0) {
            return round((float) $transaction->net_amount / (float) $transaction->quantity_ordered, 2);
        }

        return $order->packaging ? round((float) $order->packaging->price, 2) : null;
    }

    /** @return array<int, array{id: int, name: string, dimensions: string|null, price: float, is_free: bool, family_code: string|null, image: mixed}> */
    protected function getPackagingOptions(DeliveryNote $deliveryNote, ?string $familyCode): array
    {
        // The customer already paid for a specific packaging family, so the warehouse may
        // only swap to another size within that exact same family.
        if (!$familyCode || !$deliveryNote->shop?->hasPackagingAndInserts()) {
            return [];
        }

        $paidPrice = $this->paidPackagingPrice($deliveryNote);

        $options = Packaging::where('shop_id', $deliveryNote->shop_id)
            ->where('state', PackagingStateEnum::ACTIVE)
            ->where('family_code', $familyCode)
            ->when(
                $paidPrice !== null,
                fn ($query) => $query->whereRaw('ROUND(price::numeric, 2) = ?', [$paidPrice])
            )
            ->with('image')
            ->orderBy('position')
            ->orderBy('price')
            ->get();

        // A single option is the one already on the delivery note: nothing to offer, so the
        // warehouse is not shown a picker that cannot change anything.
        if ($options->count() < 2) {
            return [];
        }

        return $options
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
