<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Ordering\Order;

use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
use App\Models\Billables\Leaflet;
use App\Models\Billables\ModelHasLeaflet;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsAction;

class GetOrderInsertsWithoutArtwork
{
    use AsAction;

    /**
     * A personalised message is typed, not uploaded, so it is never counted as missing artwork.
     *
     * @return array<int, string> names of the inserts still missing their artwork
     */
    public function handle(Order $order): array
    {
        if (!$order->shop?->hasPackagingAndInserts()) {
            return [];
        }

        $leafletIds = array_filter((array) ($order->insert_types ?? []));

        if (!$leafletIds) {
            return [];
        }

        $familyCode = $order->packaging?->family_code;
        $missing    = [];

        $leaflets = Leaflet::whereIn('id', $leafletIds)
            ->where('type', '!=', LeafletTypeEnum::PERSONALISED_MESSAGE)
            ->get();

        foreach ($leaflets as $leaflet) {
            $hasArtwork = ModelHasLeaflet::where('model_type', 'Customer')
                ->where('model_id', $order->customer_id)
                ->where('shop_id', $order->shop_id)
                ->where('leaflet_id', $leaflet->id)
                ->when($familyCode, fn ($query) => $query->whereHas(
                    'packaging',
                    fn ($packaging) => $packaging->where('family_code', $familyCode)
                ))
                ->whereNotNull('media_id')
                ->exists();

            if (!$hasArtwork) {
                $missing[] = $leaflet->name;
            }
        }

        return $missing;
    }
}
