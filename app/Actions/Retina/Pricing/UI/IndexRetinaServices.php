<?php

/*
 * author Arya Permana - Kirin
 * created on 21-01-2025-10h-56m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Pricing\UI;

use App\Actions\Retina\Fulfilment\UI\IndexRetinaPricing;
use App\Actions\RetinaAction;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Http\Resources\Fulfilment\ServicesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Billables\Service;
use App\Models\Fulfilment\Fulfilment;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaServices extends RetinaAction
{
    use WithRetinaPricingSubNavigation;

    protected function getElementGroups(Fulfilment $parent): array
    {

        return [

            'state' => [
                'label' => __('State'),
                'elements' => array_merge_recursive(
                    ServiceStateEnum::labels(),
                    ServicestateEnum::count($parent->shop),
                    ServicestateEnum::shortLabels(),
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('services.state', $elements);
                },

            ],
        ];
    }

    public function handle($prefix = null): LengthAwarePaginator
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
        $queryBuilder->where('services.shop_id', $this->fulfilment->shop_id);
        $queryBuilder->join('assets', 'services.asset_id', '=', 'assets.id');
        $queryBuilder->join('currencies', 'assets.currency_id', '=', 'currencies.id');

        foreach ($this->getElementGroups($this->fulfilment) as $key => $elementGroup) {
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

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle();
    }

    public function htmlResponse(LengthAwarePaginator $services, ActionRequest $request): Response
    {
        // dd(ServicesResource::collection($services));
        return Inertia::render(
            'Pricing/RetinaServices',
            [
                'title' => __('Services'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'pageHead' => [
                    'icon' => [
                        'icon' => ['fal', 'fa-concierge-bell'],
                        'title' => __('Services'),
                    ],
                    'model' => __('Pricing'),
                    'title' => __('Services'),
                    'subNavigation' => $this->getPricingNavigation($this->fulfilment),
                ],

                'data' => ServicesResource::collection($services),
            ]
        )->table(
            $this->tableStructure(
                parent: $this->fulfilment,
            )
        );
    }

    public function tableStructure(
        Fulfilment $parent,
        ?array $modelOperations = null,
        $prefix = null,
        $canEdit = false
    ): Closure {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
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
                    match (class_basename($parent)) {
                        'Fulfilment' => [
                            'title' => __('No services found'),
                            'count' => $parent->shop->stats->number_assets_type_service,
                        ],
                        default => null
                    }
                );

            $table
                ->column(key: 'state', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'price', label: __('price'), canBeHidden: false, sortable: true, searchable: true, className: 'text-right font-mono')
                ->defaultSort('code');
        };
    }

    public function jsonResponse(LengthAwarePaginator $services): AnonymousResourceCollection
    {
        return ServicesResource::collection($services);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (array $routeParameters = []) use ($suffix) {
            return [
                [
                    'type' => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Services'),
                        'icon' => 'fal fa-bars',
                    ],
                    'suffix' => $suffix,
                ],
            ];
        };

        return
            array_merge(
                IndexRetinaPricing::make()->getBreadcrumbs(routeName: $routeName),
                $headCrumb(
                    [
                        'name' => $routeName,
                        'parameters' => $routeParameters,
                    ]
                )
            );
    }
}
