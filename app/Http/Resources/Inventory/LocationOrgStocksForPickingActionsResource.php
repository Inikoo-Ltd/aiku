<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Aug 2024 11:12:11 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property mixed $quantity
 * @property mixed $location_code
 * @property mixed $location_slug
 * @property mixed $type
 * @property mixed $location_id
 * @property mixed $picked
 * @property mixed $pickings_data
 * @property mixed $org_stock_packed_in
 */
class LocationOrgStocksForPickingActionsResource extends JsonResource
{
    public static $wrap = null;

    // ponytail: one organisation read per item row, no static memo so a settings toggle takes effect at once under Octane
    public static function collectionForPicking($locations, ?int $organisationId): mixed
    {
        $canChoose = $organisationId && (bool)data_get(
            Organisation::find($organisationId)?->settings,
            'orders.allow_picker_choose_location',
            false
        );

        if ($canChoose) {
            return self::collection($locations);
        }

        return self::collection(
            Collection::make($locations)
                ->values()
                ->filter(fn ($location, $index) => $index == 0 || filled(data_get($location, 'pickings_data')))
                ->values()
        );
    }

    public function toArray($request): array
    {
        $quantityPicked = 0;
        $pickingId      = null;
        if ($this->pickings_data) {
            $pickingsData   = preg_split('/;/', $this->pickings_data);
            $quantityPicked = $pickingsData[0] ?? 0;
            $pickingsIds    = preg_split('/,/', $pickingsData[1] ?? []);
            if (!empty($pickingsIds)) {
                $pickingId = $pickingsIds[0];
            }
        }

        $orgStockPackedIn = $this->org_stock_packed_in;

        if ($orgStockPackedIn == '') {
            $orgStockPackedIn = null;
        }

        $quantity = floor($this->quantity * 100) / 100; // Always round up to 3 decimal places

        return [
            'id'                  => $this->id,
            'location_id'         => $this->location_id,
            'location_code'       => $this->location_code,
            'location_slug'       => $this->location_slug,
            'quantity'            => $this->quantity,
            'quantity_fractional' => riseDivisor(
                divideWithRemainder(
                    findSmallestFactors($quantity)
                ),
                $orgStockPackedIn
            ),
            'type'            => $this->type,
            'quantity_picked' => $quantityPicked,
            'picking_id'      => $pickingId,
        ];
    }
}
