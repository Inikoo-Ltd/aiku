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
use Illuminate\Support\Arr;

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

    protected function orderedPackagingFamily(DeliveryNote $deliveryNote): ?string
    {
        $order = $deliveryNote->orders()->first();

        $orderedPackagingId = Arr::get($order?->data ?? [], 'ordered_packaging_id');

        $ordered = $orderedPackagingId ? Packaging::find($orderedPackagingId) : $order?->packaging;

        return $ordered?->family_code ?? $deliveryNote->packaging?->family_code;
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

    /** @return array<int, array{id: int, name: string, dimensions: string|null, price: float, is_free: bool, is_downgrade: bool, family_code: string|null, image: mixed}> */
    protected function getPackagingOptions(DeliveryNote $deliveryNote, ?string $familyCode): array
    {
        $familyCode = $this->orderedPackagingFamily($deliveryNote) ?? $familyCode;

        if (!$familyCode || !$deliveryNote->shop?->hasPackagingAndInserts()) {
            return [];
        }

        $defaultPackagingId = $deliveryNote->shop->defaultPackaging()?->id;

        $options = Packaging::where('shop_id', $deliveryNote->shop_id)
            ->where('state', PackagingStateEnum::ACTIVE)
            ->where(
                fn ($query) => $query->where('family_code', $familyCode)
                    ->orWhere('price', 0)
                    ->when($defaultPackagingId, fn ($q) => $q->orWhere('id', $defaultPackagingId))
                    ->when($deliveryNote->packaging_id, fn ($q) => $q->orWhere('id', $deliveryNote->packaging_id))
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

        $paidPrice = $this->paidPackagingPrice($deliveryNote);

        return $options
            ->map(fn (Packaging $packaging) => [
                'id'           => $packaging->id,
                'name'         => $packaging->name,
                'dimensions'   => $this->packagingDimensions($packaging),
                'price'        => (float) $packaging->price,
                'is_free'      => (float) $packaging->price === 0.0,
                'is_downgrade' => $paidPrice !== null && round((float) $packaging->price, 2) < $paidPrice,
                'family_code'  => $packaging->family_code,
                'image'        => $packaging->image ? ImageResource::make($packaging->image)->resolve() : null,
                'currency_code'   => $deliveryNote->shop->currency?->code,
            ])->all();
    }

    protected function packagingDimensions(Packaging $packaging): ?string
    {
        if (!$packaging->width || !$packaging->height) {
            return null;
        }

        return $packaging->depth
            ? "{$packaging->width} × {$packaging->height} × {$packaging->depth} mm"
            : "{$packaging->width} × {$packaging->height} mm";
    }
}
