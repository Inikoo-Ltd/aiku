<?php

/*
 * author Arya Permana - Kirin
 * created on 18-10-2024-14h-15m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Ordering\ShippingZone\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Catalogue\ShippingZonesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Ordering\ShippingZone;
use App\Models\Ordering\ShippingZoneSchema;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;

class IndexShippingZones extends OrgAction
{
    use WithCatalogueAuthorisation;


    public function handle(ShippingZoneSchema $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('shipping_zones.code', $value)
                    ->orWhereStartWith('shipping_zones.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(ShippingZone::class);

        $queryBuilder->where('shipping_zones.shipping_zone_schema_id', $parent->id);


        $queryBuilder->leftjoin('shipping_zone_stats', 'shipping_zone_stats.shipping_zone_id', '=', 'shipping_zones.id');
        $queryBuilder->leftjoin('currencies', 'shipping_zones.currency_id', '=', 'currencies.id');

        $queryBuilder
            ->defaultSort('shipping_zones.name')
            ->select([
                'shipping_zones.id',
                'shipping_zones.slug',
                'shipping_zones.code',
                'shipping_zones.name',
                'shipping_zones.price',
                'shipping_zones.territories',
                'shipping_zones.position',
                'shipping_zones.created_at',
            ]);

        return $queryBuilder->allowedSorts(['name', 'status', 'position'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(ShippingZoneSchema $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'ShippingZoneSchema' => [
                            'title' => __("No shipping zones found"),
                        ],
                        default => null
                    }
                );
            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'position', label: __('position'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'territories', label: __('territories'), canBeHidden: false);
            $table->column(key: 'price', label: __('price'), canBeHidden: false);
        };
    }

    public function jsonResponse(LengthAwarePaginator $shippingZones): AnonymousResourceCollection
    {
        return ShippingZonesResource::collection($shippingZones);
    }
}
