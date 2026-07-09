<?php

/*
 * Author: Andi Ferdiawan
 * Created: Thu, 09 Jul 2026 11:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Billables\Packaging\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Catalogue\PackagingsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Billables\Packaging;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPackagings extends OrgAction
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
                $query->whereAnyWordStartWith('packagings.name', $value)
                    ->orWhereStartWith('packagings.code', $value)
                    ->orWhereStartWith('packagings.family_code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Packaging::class);

        return $queryBuilder
            ->leftJoin('currencies', 'packagings.currency_id', '=', 'currencies.id')
            ->where('packagings.shop_id', $shop->id)
            ->defaultSort('packagings.code')
            ->select([
                'packagings.slug',
                'packagings.family_code',
                'packagings.code',
                'packagings.name',
                'packagings.type',
                'packagings.state',
                'packagings.price',
                'packagings.width',
                'packagings.height',
                'packagings.depth',
                'packagings.created_at',
                'packagings.updated_at',
                'currencies.code as currency_code',
            ])
            ->allowedSorts(['code', 'family_code', 'name', 'type', 'price'])
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
                ->defaultSort('code')
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withLabelRecord([__('packaging'), __('packagings')])
                ->withEmptyState(
                    [
                        'title'       => __('No packagings found'),
                        'description' => $canEdit ? __('You dont have any packagings yet ✨') : null,
                        'count'       => Packaging::where('shop_id', $shop->id)->count(),
                    ]
                )
                ->column(key: 'state', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'family_code', label: __('Family'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type_label', label: __('Type'), canBeHidden: false)
                ->column(key: 'dimensions', label: __('Dimensions'), canBeHidden: false)
                ->column(key: 'price', label: __('Price'), canBeHidden: false, sortable: true, align: 'right', type: 'currency');
        };
    }

    public function htmlResponse(LengthAwarePaginator $packagings, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Billables/Packagings',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Packagings'),
                'pageHead'    => [
                    'title'   => __('Packagings'),
                    'model'   => $this->shop->code,
                    'icon'    => [
                        'icon'  => ['fal', 'fa-box-open'],
                        'title' => __('Packagings')
                    ],
                    'actions' => [
                        $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('New packaging'),
                            'label'   => __('Packaging'),
                            'route'   => [
                                'name'       => 'grp.org.shops.show.billables.packagings.create',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,
                    ]
                ],
                'data'        => PackagingsResource::collection($packagings),
            ]
        )->table($this->tableStructure(shop: $this->shop, canEdit: $this->canEdit));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Packagings'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ]
        );
    }
}
