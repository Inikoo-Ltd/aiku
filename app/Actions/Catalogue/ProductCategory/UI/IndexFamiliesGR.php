<?php

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\Catalogue\MasterGoldRewardTabsEnum;
use App\Http\Resources\Catalogue\FamiliesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexFamiliesGR extends OrgAction
{
    use WithCatalogueAuthorisation;

    private Shop $parent;

    protected function getElementGroups(Shop $parent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => [
                    'active'       => [__('Active'), $parent->stats->number_families_state_active],
                    'discontinued' => [__('Discontinued'), $parent->stats->number_families_state_discontinued],
                ],
                'default' => 'active',
                'engine'  => function ($query, $elements) {
                    $states = [];
                    if (in_array('active', $elements)) {
                        $states[] = ProductCategoryStateEnum::ACTIVE->value;
                    }
                    if (in_array('discontinued', $elements)) {
                        $states[] = ProductCategoryStateEnum::DISCONTINUED->value;
                    }
                    $query->whereIn('product_categories.state', $states);
                },
            ],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(MasterGoldRewardTabsEnum::values());

        $currentTab = $this->tab ?? MasterGoldRewardTabsEnum::WITH->value;

        return $this->handle(shop: $shop, prefix: $currentTab, isGR: $currentTab === MasterGoldRewardTabsEnum::WITH->value);
    }

    public function handle(Shop $shop, ?string $prefix = null, ?bool $isGR = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('product_categories.name', $value)
                    ->orWhereStartWith('product_categories.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(\App\Models\Catalogue\ProductCategory::class);

        $queryBuilder
            ->where('product_categories.shop_id', $shop->id)
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->leftJoin('product_category_stats', 'product_categories.id', '=', 'product_category_stats.product_category_id')
            ->leftJoin('product_categories as departments', 'departments.id', '=', 'product_categories.department_id')
            ->leftJoin('product_categories as sub_departments', 'sub_departments.id', '=', 'product_categories.sub_department_id');

        foreach ($this->getElementGroups($shop) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
                default: $elementGroup['default'] ?? null,
            );
        }

        if ($isGR !== null) {
            $queryBuilder->where('product_categories.has_gr_vol_discount', $isGR);
        }

        $queryBuilder->select([
            'product_categories.id',
            'product_categories.slug',
            'product_categories.code',
            'product_categories.name',
            'product_categories.state',
            'product_categories.image_id',
            'product_categories.web_images',
            'product_category_stats.number_current_products',
            'departments.slug as department_slug',
            'departments.code as department_code',
            'departments.name as department_name',
            'sub_departments.slug as sub_department_slug',
            'sub_departments.code as sub_department_code',
            'sub_departments.name as sub_department_name',
        ]);

        return $queryBuilder
            ->defaultSort('product_categories.code')
            ->allowedSorts(['code', 'name', 'number_current_products', 'department_code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop $parent, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements'],
                    default: $elementGroup['default'] ?? null,
                );
            }

            $table
                ->defaultSort('code')
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No families found'),
                    'count' => $parent->stats->number_current_families,
                ])
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'department_code', label: __('Department'), canBeHidden: false, sortable: true)
                ->column(key: 'number_current_products', label: __('Products'), canBeHidden: false, sortable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $families): AnonymousResourceCollection
    {
        return FamiliesResource::collection($families);
    }

    public function htmlResponse(LengthAwarePaginator $families, ActionRequest $request): Response
    {
        $navigation = MasterGoldRewardTabsEnum::navigation();

        return Inertia::render(
            'Org/Catalogue/FamiliesGR',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'navigation'  => [],
                'title'       => __('Gold Reward Families'),
                'pageHead'    => [
                    'title'    => __('Gold Reward'),
                    'icon'     => ['icon' => ['fal', 'fa-medal'], 'title' => __('Gold Reward')],
                    'iconRight' => ['icon' => 'fal fa-folder'],
                    'actions'  => [],
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],
                MasterGoldRewardTabsEnum::WITH->value => $this->tab === MasterGoldRewardTabsEnum::WITH->value
                    ? fn () => FamiliesResource::collection($families)
                    : Inertia::lazy(fn () => FamiliesResource::collection(
                        $this->handle(shop: $this->parent, prefix: MasterGoldRewardTabsEnum::WITH->value, isGR: true)
                    )),
                MasterGoldRewardTabsEnum::WITHOUT->value => $this->tab === MasterGoldRewardTabsEnum::WITHOUT->value
                    ? fn () => FamiliesResource::collection($families)
                    : Inertia::lazy(fn () => FamiliesResource::collection(
                        $this->handle(shop: $this->parent, prefix: MasterGoldRewardTabsEnum::WITHOUT->value, isGR: false)
                    )),
            ]
        )
        ->table($this->tableStructure($this->parent, prefix: MasterGoldRewardTabsEnum::WITH->value))
        ->table($this->tableStructure($this->parent, prefix: MasterGoldRewardTabsEnum::WITHOUT->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        return array_merge(
            ShowCatalogue::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route'  => ['name' => $routeName, 'parameters' => $routeParameters],
                        'label'  => __('Gold Reward'),
                        'icon'   => 'fal fa-medal',
                    ],
                    'suffix' => $suffix,
                ],
            ]
        );
    }
}
