<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

/**
 * A scanner in the warehouse may read the CODE 128 on the outer packing or the EAN13 on the unit
 * inside it, so a scan is matched against both numbers, outer first.
 */
class ScanSkoBarcode extends OrgAction
{
    use WithInventoryAuthorisation;

    public function handle(Organisation $organisation, string $barcode): ?OrgStock
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }

        $query = OrgStock::where('organisation_id', $organisation->id);

        return (clone $query)->where('barcode', $barcode)->first()
            ?? (clone $query)->where('unit_barcode', $barcode)->first();
    }

    /**
     * @return array{id: int, slug: string, code: string, name: string, state: array, barcode: ?string, unit_barcode: ?string, packed_in: mixed, quantity_in_locations: mixed, image: mixed, locations: array, route: array}
     */
    public static function card(Warehouse $warehouse, OrgStock $orgStock): array
    {
        $locations = LocationOrgStock::where('org_stock_id', $orgStock->id)
            ->where('warehouse_id', $warehouse->id)
            ->with('location')
            ->get()
            ->map(fn (LocationOrgStock $locationOrgStock) => [
                'code'     => $locationOrgStock->location->code,
                'quantity' => trimDecimalZeros($locationOrgStock->quantity ?? 0),
            ])
            ->sortBy('code')
            ->values()
            ->all();

        return [
            'id'                    => $orgStock->id,
            'slug'                  => $orgStock->slug,
            'code'                  => $orgStock->code,
            'name'                  => $orgStock->name,
            'state'                 => $orgStock->state->stateIcon()[$orgStock->state->value],
            'barcode'               => $orgStock->barcode,
            'unit_barcode'          => $orgStock->unit_barcode,
            'packed_in'             => trimDecimalZeros($orgStock->packed_in),
            'quantity_in_locations' => trimDecimalZeros($orgStock->quantity_in_locations),
            'image'                 => $orgStock->tradeUnits->first()?->imageSources(0, 384),
            'locations'             => $locations,
            'route'                 => [
                'name'       => 'grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show',
                'parameters' => [
                    'organisation' => $warehouse->organisation->slug,
                    'warehouse'    => $warehouse->slug,
                    'orgStock'     => $orgStock->slug,
                ],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:64'],
        ];
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): array
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        $barcode  = $this->validatedData['barcode'];
        $orgStock = $this->handle($warehouse->organisation, $barcode);

        return [
            'barcode'    => trim($barcode),
            'found'      => (bool) $orgStock,
            'matched_on' => $orgStock ? ($orgStock->barcode === trim($barcode) ? 'sko' : 'unit') : null,
            'org_stock'  => $orgStock ? static::card($warehouse, $orgStock) : null,
        ];
    }
}
