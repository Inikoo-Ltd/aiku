<?php

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterAsset\UI\IndexMasterProducts;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Http\Resources\Masters\MasterProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMasterProductsWithMissingChildDescription extends GrpAction
{
    use WithMasterCatalogueSubNavigation;
    use WithMastersAuthorisation;

    private MasterShop $parent;

    public function handle(MasterShop $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('master_assets.code', $value)
                    ->orWhereStartWith('master_assets.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterAsset::class)
            ->leftJoin('master_asset_stats', 'master_assets.id', '=', 'master_asset_stats.master_asset_id')
            ->leftJoin('groups', 'master_assets.group_id', '=', 'groups.id')
            ->leftJoin('currencies', 'groups.currency_id', '=', 'currencies.id')
            ->leftJoin('master_product_categories as departments', 'departments.id', '=', 'master_assets.master_department_id')
            ->leftJoin('master_product_categories as families', 'families.id', '=', 'master_assets.master_family_id')
            ->where('master_assets.is_main', true)
            ->where('master_assets.master_shop_id', $parent->id)
            ->where('master_assets.has_missing_child_description', true);

        foreach (IndexMasterProducts::make()->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        $queryBuilder->select([
            'master_assets.id',
            'master_assets.code',
            'master_assets.name',
            'master_assets.slug',
            'master_assets.status',
            'master_assets.price',
            'master_assets.unit',
            'master_assets.units',
            'master_assets.rrp',
            'master_assets.web_images',
            'master_asset_stats.number_current_assets as used_in',
            'currencies.code as currency_code',
            'families.slug as master_family_slug',
            'families.code as master_family_code',
            'families.name as master_family_name',
            'departments.slug as master_department_slug',
            'departments.code as master_department_code',
            'departments.name as master_department_name',
        ]);

        return $queryBuilder
            ->defaultSort('master_assets.code')
            ->allowedSorts(['code', 'name', 'used_in'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(MasterShop $parent, ?array $modelOperations = null, ?string $prefix = null): \Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            foreach (IndexMasterProducts::make()->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(['title' => __('No master products found')]);

            $table
                ->column(key: 'status_icon', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'master_family_code', label: __('Family'), canBeHidden: false, sortable: true)
                ->column(key: 'used_in', label: __('Used in'), tooltip: __('Current products with this master'), canBeHidden: false, sortable: true)
                ->defaultSort('code');
        };
    }

    public function jsonResponse(LengthAwarePaginator $masterAssets): AnonymousResourceCollection
    {
        return MasterProductsResource::collection($masterAssets);
    }

    public function htmlResponse(LengthAwarePaginator $masterAssets, ActionRequest $request): Response
    {
        $subNavigation = $this->getMasterShopNavigation($this->parent);

        return Inertia::render(
            'Masters/MasterMissingChildDescriptionProducts',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Master Products').' ('.__('Missing child description').')',
                'pageHead'    => [
                    'title'         => $this->parent->name,
                    'icon'          => [
                        'icon'  => ['fal', 'fa-store-alt'],
                        'title' => __('Master shop'),
                    ],
                    'model'         => '',
                    'afterTitle'    => [
                        'label' => __('Master Products').' ('.__('Missing child description').')',
                    ],
                    'iconRight'     => ['icon' => 'fal fa-align-left'],
                    'subNavigation' => $subNavigation,
                ],
                'data'        => MasterProductsResource::collection($masterAssets),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Master Products'),
                        'icon'  => 'fal fa-bars',
                    ],
                    'suffix' => trim('('.__('Missing child description').') '.$suffix),
                ],
            ];
        };

        return match ($routeName) {
            'grp.masters.master_shops.show.master_products_missing_child_description' =>
            array_merge(
                ShowMasterShop::make()->getBreadcrumbs($this->parent),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => Arr::only($routeParameters, ['masterShop']),
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }

    public function inMasterShop(MasterShop $masterShop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterShop;
        $this->initialisation(group(), $request);

        return $this->handle($masterShop);
    }
}
