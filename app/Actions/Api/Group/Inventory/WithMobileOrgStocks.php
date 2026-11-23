<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-09h-42m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inventory;

use App\Actions\Inventory\OrgStock\Json\ScanSkoBarcode;
use App\Actions\Inventory\OrgStock\UI\GetOrgStockShowcase;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

trait WithMobileOrgStocks
{
    use WithMobileInventoryAccess;
    use WithMobileStockFractions;

    public const int MAX_ID = 2147483647;

    protected function ensureOrgStockOfWarehouse(OrgStock $orgStock, Warehouse $warehouse): void
    {
        abort_unless($orgStock->organisation_id === $warehouse->organisation_id, 404);
    }

    protected function ensureCanEditStocks(User $user, Warehouse $warehouse): void
    {
        abort_unless(self::canEditStocks($user, $warehouse), 403, __('You need stock edit rights to change the stock.'));
    }

    protected function ensureCanChangeOrgStock(User $user, Warehouse $warehouse, OrgStock $orgStock): void
    {
        $this->ensureCanViewInventory($user, $warehouse);
        $this->ensureOrgStockOfWarehouse($orgStock, $warehouse);
        $this->ensureCanEditStocks($user, $warehouse);
    }

    /**
     * @return array{0: Warehouse, 1: OrgStock}
     */
    protected function ensureCanChangeLink(User $user, LocationOrgStock $locationOrgStock): array
    {
        $warehouse = $locationOrgStock->warehouse;
        $this->ensureCanViewInventory($user, $warehouse);
        $this->ensureCanEditStocks($user, $warehouse);
        abort_unless($this->warehouseLinks($locationOrgStock->orgStock, $warehouse)->whereKey($locationOrgStock->id)->exists(), 404);

        return [$warehouse, $locationOrgStock->orgStock];
    }

    protected function warehouseLinks(OrgStock $orgStock, Warehouse $warehouse): Builder
    {
        return LocationOrgStock::query()
            ->where('location_org_stocks.org_stock_id', $orgStock->id)
            ->where('location_org_stocks.warehouse_id', $warehouse->id)
            ->whereHas('location', fn (Builder $query) => $query->where('warehouse_id', $warehouse->id));
    }

    /**
     * @param array<int, int|string> $ids
     * @return Collection<int, LocationOrgStock>
     */
    protected function ownLinks(OrgStock $orgStock, Warehouse $warehouse, array $ids): Collection
    {
        $links = $this->warehouseLinks($orgStock, $warehouse)->whereIn('id', $ids)->with('location')->get()->keyBy('id');
        abort_unless(collect($ids)->every(fn ($id) => $links->has((int) $id)), 404);

        return $links;
    }

    /**
     * @return array<string, mixed>
     */
    protected function orgStockDetail(Warehouse $warehouse, OrgStock $orgStock, User $user): array
    {
        $orgStock->loadMissing(['orgStockFamily', 'stats', 'tradeUnits.image', 'organisation.currency']);
        $showcase  = GetOrgStockShowcase::make();
        $summary   = $showcase->getSummary($orgStock);
        $cover     = $showcase->getCover($orgStock);
        $family    = $orgStock->orgStockFamily;
        $pickCodes = Location::whereIn('id', array_filter([$orgStock->picking_location_id, $orgStock->picking_dropshipping_location_id]))->pluck('code', 'id')->all();

        return [
            'id'                            => $orgStock->id,
            'code'                          => $orgStock->code,
            'name'                          => $orgStock->name,
            'state'                         => $orgStock->state->value,
            'state_label'                   => OrgStockStateEnum::labels()[$orgStock->state->value],
            'family'                        => $family ? [
                'id'   => $family->id,
                'code' => $family->code,
                'name' => $family->name,
            ] : null,
            'image_url'                     => $this->orgStockImageUrl($orgStock),
            'barcode'                       => $orgStock->barcode,
            'unit_barcode'                  => $orgStock->unit_barcode,
            'packed_in'                     => $orgStock->packed_in === null ? null : (int) $orgStock->packed_in,
            'is_on_demand'                  => (bool) $orgStock->is_on_demand,
            'note_to_pickers'               => $orgStock->note_to_pickers,
            'note_to_packers'               => $orgStock->note_to_packers,
            'currency_code'                 => $orgStock->organisation->currency->code,
            'stock_value'                   => (float) $orgStock->stats?->stock_value,
            ...$this->quantityFields('quantity_in_locations', round((float) LocationOrgStock::where('org_stock_id', $orgStock->id)->sum('quantity'), 3), $orgStock->packed_in),
            ...$this->quantityFields('quantity_reserved', $summary['quantity_in_submitted_orders']['value'], $orgStock->packed_in),
            ...$this->quantityFields('quantity_to_be_picked', $summary['quantity_to_be_picked']['value'], $orgStock->packed_in),
            'cover'                         => $cover ? [
                'days'        => $cover['days'],
                'out_at'      => $cover['out_at'] ? Carbon::parse($cover['out_at'])->toIso8601String() : null,
                'daily_usage' => $cover['daily_usage'],
            ] : null,
            'picking_location'              => $this->locationReference($orgStock->picking_location_id, $pickCodes),
            'picking_dropshipping_location' => $this->locationReference($orgStock->picking_dropshipping_location_id, $pickCodes),
            'locations'                     => $this->warehouseLinks($orgStock, $warehouse)
                ->with('location.warehouseArea')
                ->get()
                ->sortBy([
                    fn (LocationOrgStock $a, LocationOrgStock $b) => ($b->type === LocationStockTypeEnum::PICKING) <=> ($a->type === LocationStockTypeEnum::PICKING),
                    fn (LocationOrgStock $a, LocationOrgStock $b) => $a->location->code <=> $b->location->code,
                ])
                ->map(fn (LocationOrgStock $link) => $this->linkRow($link, $orgStock->packed_in))
                ->values()
                ->all(),
            'can'                           => [
                'edit_stocks'    => self::canEditStocks($user, $warehouse),
                'edit_locations' => self::canEditLocations($user, $warehouse),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function linkRow(LocationOrgStock $link, int|float|string|null $packedIn): array
    {
        $location = $link->location;
        $area     = $location->warehouseArea;

        return [
            'location_org_stock_id'   => $link->id,
            'type'                    => $link->type?->value,
            ...$this->quantityFields('quantity', $link->quantity, $packedIn),
            'picking_priority'        => $link->picking_priority,
            'notes'                   => $link->notes,
            'min_stock'               => $this->setting($link, 'min_stock'),
            'max_stock'               => $this->setting($link, 'max_stock'),
            'replenishment_stock'     => $this->setting($link, 'replenishment_stock'),
            'is_default_wholesale'    => (bool) $link->default_wholesale_picking_location,
            'is_default_dropshipping' => (bool) $link->default_dropshipping_picking_location,
            'audited_at'              => $link->audited_at ? Carbon::parse($link->audited_at)->toIso8601String() : null,
            'location'                => [
                'id'           => $location->id,
                'code'         => $location->code,
                'status'       => $location->status->value,
                'allow_stocks' => (bool) $location->allow_stocks,
                'area'         => $area ? [
                    'id'   => $area->id,
                    'code' => $area->code,
                    'name' => $area->name,
                ] : null,
            ],
            'version'                 => (int) $link->version,
        ];
    }

    protected function respondWithOrgStock(Warehouse $warehouse, OrgStock $orgStock, User $user, string $status, string $message): JsonResponse
    {
        return response()->json([
            'data'   => $this->orgStockDetail($warehouse, $orgStock->refresh(), $user),
            'result' => [
                'status'  => $status,
                'message' => $message,
            ],
        ]);
    }


    protected function orgStockImageUrl(OrgStock $orgStock): ?string
    {
        return Arr::get(ScanSkoBarcode::imageOf($orgStock) ?? [], 'original');
    }

    /**
     * @param array<int, string> $codes
     * @return array{id: int, code: string}|null
     */
    protected function locationReference(?int $locationId, array $codes): ?array
    {
        return $locationId && isset($codes[$locationId]) ? ['id' => $locationId, 'code' => $codes[$locationId]] : null;
    }

    /**
     * @return array{id: int, name: string|null}|null
     */
    protected function userReference(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->contact_name ?: $user->username] : null;
    }

    private function setting(LocationOrgStock $link, string $key): ?float
    {
        $value = Arr::get($link->settings ?? [], $key);

        return $value === null || $value === '' ? null : (float) $value;
    }
}
