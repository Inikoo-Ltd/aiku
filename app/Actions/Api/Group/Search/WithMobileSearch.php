<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-51m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Search;

use App\Actions\Api\Group\Fulfilment\IndexMobilePalletDeliveries;
use App\Actions\Api\Group\Fulfilment\IndexMobilePalletReturns;
use App\Actions\Api\Group\GoodsIn\IndexMobileStockDeliveries;
use App\Actions\Api\Group\GoodsOut\GetMobileDeliveryNoteCounts;
use App\Actions\Api\Group\GoodsOut\WithMobileDeliveryNotes;
use App\Actions\Api\Group\WithMobileModuleAccess;
use App\Actions\GoodsIn\StockDelivery\UI\IndexStockDeliveries;
use App\Actions\Inventory\OrgStock\Json\ScanSkoBarcode;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Inventory\Location\LocationStatusEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Inventory\OrgStockFamily\OrgStockFamilyStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait WithMobileSearch
{
    use WithMobileDeliveryNotes;
    use WithMobileModuleAccess;

    public const array SEARCH_TYPES = [
        'skos'              => 'sko',
        'sko_families'      => 'sko_family',
        'locations'         => 'location',
        'delivery_notes'    => 'delivery_note',
        'stock_deliveries'  => 'stock_delivery',
        'returns'           => 'return',
        'pallets'           => 'pallet',
        'pallet_deliveries' => 'pallet_delivery',
        'pallet_returns'    => 'pallet_return',
    ];

    /**
     * @return array<int, string>
     */
    protected function searchableTypes(User $user, Warehouse $warehouse): array
    {
        $canViewInventory  = self::canViewInventory($user, $warehouse);
        $canViewFulfilment = self::canViewFulfilment($user, $warehouse);

        return array_keys(array_filter([
            'skos'              => $canViewInventory,
            'sko_families'      => $canViewInventory,
            'locations'         => self::canViewLocations($user, $warehouse),
            'delivery_notes'    => self::canViewGoodsOut($user, $warehouse),
            'stock_deliveries'  => self::canViewGoodsIn($user, $warehouse),
            'returns'           => self::canViewReturns($user, $warehouse),
            'pallets'           => $canViewFulfilment,
            'pallet_deliveries' => $canViewFulfilment,
            'pallet_returns'    => $canViewFulfilment,
        ]));
    }

    /**
     * @return array<string, string>
     */
    protected function searchTypeLabels(): array
    {
        return [
            'skos'              => __('SKOs'),
            'sko_families'      => __('SKO Families'),
            'locations'         => __('Locations'),
            'delivery_notes'    => __('Delivery notes'),
            'stock_deliveries'  => __('Stock deliveries'),
            'returns'           => __('Returns'),
            'pallets'           => __('Pallets'),
            'pallet_deliveries' => __('Pallet deliveries'),
            'pallet_returns'    => __('Pallet returns'),
        ];
    }

    protected function searchTypeQuery(string $type, Warehouse $warehouse): Builder
    {
        return match ($type) {
            'skos'              => OrgStock::query()
                ->where('org_stocks.organisation_id', $warehouse->organisation_id)
                ->select(['org_stocks.id', 'org_stocks.code', 'org_stocks.name', 'org_stocks.state', 'org_stocks.quantity_in_locations'])
                ->with('tradeUnits.image'),
            'sko_families'      => OrgStockFamily::query()
                ->leftJoin('org_stock_family_stats', 'org_stock_family_stats.org_stock_family_id', 'org_stock_families.id')
                ->where('org_stock_families.organisation_id', $warehouse->organisation_id)
                ->select(['org_stock_families.id', 'org_stock_families.code', 'org_stock_families.name', 'org_stock_families.state', 'org_stock_family_stats.number_current_org_stocks']),
            'locations'         => Location::query()
                ->leftJoin('location_stats', 'location_stats.location_id', 'locations.id')
                ->leftJoin('warehouse_areas', 'warehouse_areas.id', 'locations.warehouse_area_id')
                ->where('locations.warehouse_id', $warehouse->id)
                ->select(['locations.id', 'locations.code', 'locations.status', 'warehouse_areas.code as area_code', 'location_stats.number_org_stock_slots']),
            'delivery_notes'    => DeliveryNote::query()
                ->join('shops', 'shops.id', 'delivery_notes.shop_id')
                ->where('delivery_notes.warehouse_id', $warehouse->id)
                ->where('shops.is_aiku', true)
                ->select(['delivery_notes.id', 'delivery_notes.reference', 'delivery_notes.state', 'delivery_notes.number_items'])
                ->selectRaw(Shop::dispatchingChannelSql().' as dispatching_channel')
                ->selectRaw('exists (select 1 from org_partners where org_partners.customer_id = delivery_notes.customer_id) as is_partner_customer'),
            'stock_deliveries'  => StockDelivery::query()
                ->where('stock_deliveries.organisation_id', $warehouse->organisation_id)
                ->select(['stock_deliveries.id', 'stock_deliveries.reference', 'stock_deliveries.state', 'stock_deliveries.parent_name']),
            'returns'           => ReturnDeliveryNote::query()
                ->join('shops', 'shops.id', 'return_delivery_notes.shop_id')
                ->leftJoin('customers', 'customers.id', 'return_delivery_notes.customer_id')
                ->where('return_delivery_notes.warehouse_id', $warehouse->id)
                ->where('shops.is_aiku', true)
                ->select(['return_delivery_notes.id', 'return_delivery_notes.reference', 'return_delivery_notes.state', 'customers.name as customer_name']),
            'pallets'           => Pallet::query()
                ->leftJoin('locations', 'locations.id', 'pallets.location_id')
                ->where('pallets.warehouse_id', $warehouse->id)
                ->select(['pallets.id', 'pallets.reference', 'pallets.customer_reference', 'pallets.state', 'locations.code as location_code']),
            'pallet_deliveries' => PalletDelivery::query()
                ->leftJoin('fulfilment_customers', 'fulfilment_customers.id', 'pallet_deliveries.fulfilment_customer_id')
                ->leftJoin('customers', 'customers.id', 'fulfilment_customers.customer_id')
                ->where('pallet_deliveries.warehouse_id', $warehouse->id)
                ->select(['pallet_deliveries.id', 'pallet_deliveries.reference', 'pallet_deliveries.customer_reference', 'pallet_deliveries.state', 'customers.name as customer_name']),
            'pallet_returns'    => PalletReturn::query()
                ->leftJoin('fulfilment_customers', 'fulfilment_customers.id', 'pallet_returns.fulfilment_customer_id')
                ->leftJoin('customers', 'customers.id', 'fulfilment_customers.customer_id')
                ->where('pallet_returns.warehouse_id', $warehouse->id)
                ->select(['pallet_returns.id', 'pallet_returns.reference', 'pallet_returns.customer_reference', 'pallet_returns.state', 'pallet_returns.type', 'customers.name as customer_name']),
        };
    }

    protected function searchMatchesQuery(string $type, Warehouse $warehouse, string $search): Builder
    {
        $query = $this->searchTypeQuery($type, $warehouse);

        return match ($type) {
            'skos'              => $query
                ->whereIn('org_stocks.state', [OrgStockStateEnum::ACTIVE, OrgStockStateEnum::DISCONTINUING])
                ->where(fn (Builder $query) => $query
                    ->whereStartWith('org_stocks.code', $search)
                    ->orWhereAnyWordStartWith('org_stocks.name', $search)
                    ->orWhereHas('orgSupplierProducts.supplierProduct', fn (Builder $query) => $query->whereStartWith('supplier_products.code', $search)))
                ->orderBy('org_stocks.code')
                ->orderBy('org_stocks.id'),
            'sko_families'      => $query
                ->where('org_stock_families.state', OrgStockFamilyStateEnum::ACTIVE)
                ->where(fn (Builder $query) => $query
                    ->whereStartWith('org_stock_families.code', $search)
                    ->orWhereAnyWordStartWith('org_stock_families.name', $search))
                ->orderBy('org_stock_families.code')
                ->orderBy('org_stock_families.id'),
            'locations'         => $query
                ->whereStartWith('locations.code', $search)
                ->orderBy('locations.code')
                ->orderBy('locations.id'),
            'delivery_notes'    => $query
                ->where(fn (Builder $query) => $query
                    ->whereRaw("lower(delivery_notes.reference) LIKE lower(?) || '%'", [$this->escapeLikeWildcards($search)])
                    ->orWhereWith('delivery_notes.tracking_number', $search))
                ->orderByRaw("delivery_notes.state in ('".DeliveryNoteStateEnum::DISPATCHED->value."', '".DeliveryNoteStateEnum::CANCELLED->value."')")
                ->orderByDesc('delivery_notes.date')
                ->orderByDesc('delivery_notes.id'),
            'stock_deliveries'  => $query
                ->whereIn('stock_deliveries.state', IndexMobileStockDeliveries::ACTIVE_STOCK_DELIVERY_STATES)
                ->where(fn (Builder $query) => IndexStockDeliveries::whereGlobalSearch($query, $search))
                ->orderByRaw('stock_deliveries.date desc nulls last')
                ->orderByDesc('stock_deliveries.id'),
            'returns'           => $query
                ->whereWith('return_delivery_notes.reference', $search)
                ->orderByDesc('return_delivery_notes.created_at')
                ->orderByDesc('return_delivery_notes.id'),
            'pallets'           => $query
                ->where('pallets.status', PalletStatusEnum::STORING)
                ->where(fn (Builder $query) => $query
                    ->whereStartWith('pallets.reference', $search)
                    ->orWhereWith('pallets.customer_reference', $search)
                    ->orWhereStartWith('locations.code', $search))
                ->orderByRaw('pallets.reference asc nulls last')
                ->orderBy('pallets.id'),
            'pallet_deliveries' => $query
                ->whereIn('pallet_deliveries.state', IndexMobilePalletDeliveries::ACTIVE_PALLET_DELIVERY_STATES)
                ->where(fn (Builder $query) => $query
                    ->whereStartWith('pallet_deliveries.reference', $search)
                    ->orWhereStartWith('pallet_deliveries.customer_reference', $search)
                    ->orWhereWith('customers.name', $search))
                ->orderByRaw('pallet_deliveries.estimated_delivery_date asc nulls last')
                ->orderByDesc('pallet_deliveries.id'),
            'pallet_returns'    => $query
                ->whereIn('pallet_returns.state', IndexMobilePalletReturns::ACTIVE_STATES)
                ->where(fn (Builder $query) => $query
                    ->whereStartWith('pallet_returns.reference', $search)
                    ->orWhereStartWith('pallet_returns.customer_reference', $search)
                    ->orWhereStartWith('pallet_returns.slug', $search)
                    ->orWhereWith('customers.name', $search))
                ->orderByRaw('COALESCE(pallet_returns.confirmed_at, pallet_returns.date) desc nulls last')
                ->orderByDesc('pallet_returns.id'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function searchRow(string $type, Model $model): array
    {
        return [
            'type'       => self::SEARCH_TYPES[$type],
            'id'         => $model->getKey(),
            'code'       => null,
            'title'      => null,
            'subtitle'   => null,
            'state'      => null,
            'state_label' => null,
            'quantity'   => null,
            'image_url'  => null,
            'type_group' => null,
            ...match ($type) {
                'skos'              => $this->skoSearchFields($model),
                'sko_families'      => $this->recordSearchFields($model->code, [$model->name, __(':count SKOs', ['count' => (int) $model->number_current_org_stocks])], $model->state->value, OrgStockFamilyStateEnum::labels()),
                'locations'         => $this->recordSearchFields($model->code, [$model->area_code, __(':count SKOs', ['count' => (int) $model->number_org_stock_slots])], $model->status->value, $this->locationStatusLabels()),
                'delivery_notes'    => $this->recordSearchFields($model->reference, [$this->deliveryNoteChannelLabel($model), __(':count items', ['count' => (int) $model->number_items])], $model->state->value, DeliveryNoteStateEnum::labels()),
                'stock_deliveries'  => $this->recordSearchFields($model->reference, [$model->parent_name], $model->state->value, StockDeliveryStateEnum::labels()),
                'returns'           => $this->recordSearchFields($model->reference, [$model->customer_name], $model->state->value, ReturnDeliveryNoteStateEnum::labels()),
                'pallets'           => $this->recordSearchFields($model->reference, [$model->customer_reference, $model->location_code], $model->state->value, PalletStateEnum::labels()),
                'pallet_deliveries' => $this->recordSearchFields($model->reference, [$model->customer_name, $model->customer_reference], $model->state->value, PalletDeliveryStateEnum::labels()),
                'pallet_returns'    => [
                    ...$this->recordSearchFields($model->reference, [$model->customer_name, $model->customer_reference], $model->state->value, PalletReturnStateEnum::labels()),
                    'type_group' => $model->type === PalletReturnTypeEnum::PALLET ? 'pallets' : 'items',
                ],
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function skoSearchFields(OrgStock $orgStock): array
    {
        return [
            ...$this->recordSearchFields($orgStock->code, [$orgStock->name], $orgStock->state->value, OrgStockStateEnum::labels()),
            'quantity'  => (float) $orgStock->quantity_in_locations,
            'image_url' => Arr::get(ScanSkoBarcode::imageOf($orgStock) ?? [], 'original'),
        ];
    }

    /**
     * @param array<int, string|null> $subtitleParts
     * @param array<string, string> $stateLabels
     * @return array{code: string|null, title: string|null, subtitle: string|null, state: string, state_label: string|null}
     */
    private function recordSearchFields(?string $code, array $subtitleParts, string $state, array $stateLabels): array
    {
        $subtitle = implode(' · ', array_filter($subtitleParts, fn (?string $part) => filled($part)));

        return [
            'code'        => $code,
            'title'       => $code,
            'subtitle'    => $subtitle === '' ? null : $subtitle,
            'state'       => $state,
            'state_label' => $stateLabels[$state] ?? null,
        ];
    }

    private function escapeLikeWildcards(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    private function deliveryNoteChannelLabel(DeliveryNote $deliveryNote): ?string
    {
        $channel = $this->dispatchingChannel((string) $deliveryNote->dispatching_channel, (bool) $deliveryNote->is_partner_customer);

        return GetMobileDeliveryNoteCounts::channelLabels()[$channel] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function locationStatusLabels(): array
    {
        return [
            LocationStatusEnum::OPERATIONAL->value => __('Operational'),
            LocationStatusEnum::BROKEN->value      => __('Broken'),
        ];
    }
}
