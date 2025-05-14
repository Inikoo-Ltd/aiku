<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 04-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Billables\Service\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Enums\UI\Fulfilment\ServicesTabsEnum;
use App\Http\Resources\Fulfilment\ServicesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Billables\Service;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexShopServices extends OrgAction
{
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request)->withTab(ServicesTabsEnum::values());

        return $this->handle($shop, ServicesTabsEnum::SERVICES->value);
    }


    protected function getElementGroups(Shop $parent): array
    {
        return [

            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    ServicestateEnum::labels(),
                    ServicestateEnum::count($parent),
                    ServicestateEnum::shortLabels(),
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('services.state', $elements);
                }

            ],
        ];
    }

    public function handle(Shop $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('services.name', $value)
                    ->orWhereStartWith('services.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Service::class);
        $queryBuilder->where('services.shop_id', $parent->id);
        $queryBuilder->join('assets', 'services.asset_id', '=', 'assets.id');
        $queryBuilder->join('currencies', 'assets.currency_id', '=', 'currencies.id');


        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        $queryBuilder
            ->defaultSort('services.id')
            ->select([
                'services.id',
                'services.slug',
                'services.state',
                'services.created_at',
                'services.price',
                'services.unit',
                'assets.name',
                'assets.code',
                'assets.current_historic_asset_id as historic_asset_id',
                'services.description',
                'currencies.code as currency_code',
                'services.is_auto_assign',
                'services.auto_assign_trigger',
                'services.auto_assign_subject',
                'services.auto_assign_subject_type',
                'services.auto_assign_status',
            ]);


        return $queryBuilder->allowedSorts(['code', 'price', 'name', 'state'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function htmlResponse(LengthAwarePaginator $services, ActionRequest $request): Response
    {
        $actions = null;


        return Inertia::render(
            'Org/Billables/Services',
            [
                'title'       => __('Services'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'icon'    => [
                        'icon'  => ['fal', 'fa-concierge-bell'],
                        'title' => __('Services')
                    ],
                    'model'   => __('Catalogue'),
                    'title'   => __('Services'),
                    'actions' => $actions
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => ServicesTabsEnum::navigation(),
                ],

                ServicesTabsEnum::SERVICES->value => $this->tab == ServicesTabsEnum::SERVICES->value ?
                    fn () => ServicesResource::collection($services)
                    : Inertia::lazy(fn () => ServicesResource::collection($services)),

            ]
        )->table(
            $this->tableStructure(
                shop: $this->shop,
                prefix: ServicesTabsEnum::SERVICES->value
            )
        );
    }

    public function tableStructure(
        Shop $shop,
        ?array $modelOperations = null,
        $prefix = null,
    ): Closure {
        return function (InertiaTable $table) use ($shop, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($shop) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($shop)) {
                        'Fulfilment' => [
                            'title' => __("No services found"),
                            'count' => $shop->stats->number_assets_type_service,
                        ],
                        default => null
                    }
                );

            $table
                ->column(key: 'state', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'price', label: __('price'), canBeHidden: false, sortable: true, searchable: true, className: 'text-right font-mono')
                ->column(key: 'workflow', label: __('workflow'), canBeHidden: false)
                ->defaultSort('code');
        };
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Services'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            $headCrumb(
                [
                    'name'       => $routeName,
                    'parameters' => $routeParameters
                ],
                $suffix
            )
        );
    }


}
