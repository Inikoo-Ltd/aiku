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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

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

    /**
     * What the customer asked for at checkout, which is what the family on offer in the
     * warehouse is anchored to. Once the warehouse swaps the packaging the order carries
     * the new one, so the original is read from the snapshot the swap leaves behind.
     */
    protected function orderedPackaging(DeliveryNote $deliveryNote): ?Packaging
    {
        $order = $deliveryNote->orders()->first();

        if (!$order) {
            return null;
        }

        $orderedId = Arr::get($order->data ?? [], 'ordered_packaging_id');

        return $orderedId
            ? Packaging::where('id', $orderedId)->where('shop_id', $deliveryNote->shop_id)->first()
            : $order->packaging;
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

    /** @return array<int, array{id: int, name: string, dimensions: string|null, price: float, is_free: bool, is_downgrade: bool, is_fallback: bool, family_code: string|null, image: mixed}> */
    protected function getPackagingOptions(DeliveryNote $deliveryNote, ?string $familyCode): array
    {
        if (!$deliveryNote->shop?->hasPackagingAndInserts()) {
            return [];
        }

        $orderedPackaging = $this->orderedPackaging($deliveryNote);
        $defaultPackaging = $deliveryNote->shop->defaultPackaging();

        /* The warehouse packs what the customer paid for, so only that family is on offer,
           with the shop default kept beside it for the items that do not fit. The ordered
           and current packaging are pinned in as well, so a picker who swapped to the
           default still finds the way back to the family they came from. */
        $anchorFamily = $orderedPackaging?->family_code ?: $familyCode;
        $pinnedIds    = array_filter([
            $defaultPackaging?->id,
            $orderedPackaging?->id,
            $this->effectivePackaging($deliveryNote)?->id,
        ]);

        if (!$anchorFamily && !$pinnedIds) {
            return [];
        }

        $options = Packaging::where('shop_id', $deliveryNote->shop_id)
            ->where('state', PackagingStateEnum::ACTIVE)
            ->where(function (Builder $query) use ($anchorFamily, $pinnedIds) {
                if ($anchorFamily) {
                    $query->where('family_code', $anchorFamily);
                }

                if ($pinnedIds) {
                    $query->orWhereIn('id', $pinnedIds);
                }
            })
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
                'is_fallback'  => $defaultPackaging !== null
                    && $packaging->id === $defaultPackaging->id
                    && $packaging->family_code !== $anchorFamily,
                'family_code'  => $packaging->family_code,
                'family_name'  => Arr::get($packaging->data ?? [], 'family_name') ?? Str::headline((string) $packaging->family_code),
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
