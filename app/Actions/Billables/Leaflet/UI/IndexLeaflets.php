<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 10:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Billables\Leaflet\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Catalogue\LeafletsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Billables\Leaflet;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexLeaflets extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('leaflets.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Leaflet::class);

        return $queryBuilder
            ->leftJoin('currencies', 'leaflets.currency_id', '=', 'currencies.id')
            ->leftJoin('packagings', 'leaflets.packaging_id', '=', 'packagings.id')
            ->where('leaflets.shop_id', $shop->id)
            ->defaultSort('leaflets.name')
            ->select([
                'leaflets.id',
                'leaflets.name',
                'leaflets.type',
                'leaflets.state',
                'leaflets.price',
                'leaflets.created_at',
                'leaflets.updated_at',
                'currencies.code as currency_code',
                'packagings.code as packaging_code',
            ])
            ->allowedSorts(['name', 'type', 'price'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop $shop, ?array $modelOperations = null, $prefix = null, $canEdit = false): Closure
    {
        return function (InertiaTable $table) use ($shop, $modelOperations, $prefix, $canEdit) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->defaultSort('name')
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withLabelRecord([__('leaflet'), __('leaflets')])
                ->withEmptyState(
                    [
                        'title'       => __('No leaflets found'),
                        'description' => $canEdit ? __('You dont have any leaflets yet ✨') : null,
                        'count'       => Leaflet::where('shop_id', $shop->id)->count(),
                    ]
                )
                ->column(key: 'state', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type_label', label: __('Type'), canBeHidden: false)
                ->column(key: 'packaging_code', label: __('Packaging'), canBeHidden: false)
                ->column(key: 'price', label: __('Price'), canBeHidden: false, sortable: true, align: 'right', type: 'currency');
        };
    }

    public function jsonResponse(LengthAwarePaginator $leaflets): AnonymousResourceCollection
    {
        return LeafletsResource::collection($leaflets);
    }
}
