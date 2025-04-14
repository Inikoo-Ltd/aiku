<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jan 2024 18:40:36 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet\UI;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Http\Resources\Fulfilment\MayaPalletsResource;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Location;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPallets extends OrgAction
{
    use WithFulfilmentShopAuthorisation;
    use WithPalletsSubNavigation;



    private Group|Fulfilment $parent;

    protected function getElementGroups(Group|Fulfilment $parent): array
    {
        return [
            'status' => [
                'label'    => __('Status'),
                'elements' => array_merge_recursive(
                    PalletStatusEnum::labels($parent),
                    PalletStatusEnum::count($parent)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('pallets.status', $elements);
                }
            ],


        ];
    }

    public function handle(Group|Fulfilment|Location $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('pallets.customer_reference', $value)
                    ->orWhereWith('pallets.reference', $value);
            });
        });


        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Pallet::class);

        if ($parent instanceof Group) {
            $query->where('pallets.group_id', $parent->id);
        } elseif ($parent instanceof Location) {
            $query->where('pallets.location_id', $parent->id);
        } else {
            $query->where('pallets.fulfilment_id', $parent->id);
            $query->whereNotIn('pallets.status', ['in_process', 'not_received', 'returned', 'incident']);
        }

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }


        $query->whereNotNull('pallets.slug');


        $query->defaultSort('pallets.id')
            ->select(
                'pallets.id',
                'pallets.slug',
                'pallets.reference',
                'pallets.customer_reference',
                'pallets.notes',
                'pallets.state',
                'pallets.status',
                'pallets.rental_id',
                'pallets.type',
                'pallets.received_at',
                'pallets.location_id',
                'pallets.fulfilment_customer_id',
                'pallets.warehouse_id',
                'pallets.pallet_delivery_id',
                'pallets.pallet_return_id',
                'organisations.slug as organisation_slug',
                'organisations.name as organisation_name',
                'fulfilments.slug as fulfilment_slug',
            );
        $query->leftJoin('fulfilments', 'pallets.fulfilment_id', 'fulfilments.id');
        $query->leftJoin('organisations', 'pallets.organisation_id', 'organisations.id');
        $query->leftJoin('fulfilment_customers', 'fulfilment_customers.id', 'pallets.fulfilment_customer_id');
        $query->leftJoin('customers', 'customers.id', 'fulfilment_customers.customer_id');
        $query->addSelect('customers.name as fulfilment_customer_name', 'customers.slug as fulfilment_customer_slug');


        $query->leftJoin('locations', 'locations.id', 'pallets.location_id');
        $query->addSelect('locations.code as location_code', 'locations.slug as location_slug');

        return $query->allowedSorts([
            'organisation_name',
            'customer_reference',
            'reference',
            'fulfilment_customer_name',
        ])
            ->allowedFilters([$globalSearch, 'customer_reference', 'reference'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Fulfilment|Location $parent, $prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations, $parent) {
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


            $emptyStateData = [
                'icons' => ['fal fa-pallet'],
                'title' => __('No pallets found'),
                'count' => $parent->stats->number_pallets
            ];


            $emptyStateData['description'] = __("There is not pallets in this fulfilment shop");


            $table->withGlobalSearch();


            $table->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);

            $table->column(key: 'type_icon', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');


            $table->column(key: 'fulfilment_customer_name', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'customer_reference', label: __("Pallet reference (customer's), notes"), shortLabel: 'PR/N', canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'contents', label: __('Contents'), canBeHidden: false, searchable: true);


            $table->defaultSort('reference');
        };
    }


    public function jsonResponse(LengthAwarePaginator $pallets, ActionRequest $request): AnonymousResourceCollection
    {
        if ($request->hasHeader('Maya-Version')) {
            return MayaPalletsResource::collection($pallets);
        }
        return PalletsResource::collection($pallets);
    }


    public function htmlResponse(LengthAwarePaginator $pallets, ActionRequest $request): Response
    {
        $subNavigation = null;
        if (!($this->parent instanceof Group)) {
            $subNavigation = $this->getPalletsInWarehouseSubNavigation($this->parent, $request);
        }

        $title      = __('Pallets');
        $icon       = ['fal', 'fa-pallet'];
        $afterTitle = null;
        $iconRight  = null;

        return Inertia::render(
            'Org/Fulfilment/Pallets',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Pallets'),
                'pageHead'    => [
                    'title'      => $title,
                    'afterTitle' => $afterTitle,
                    'iconRight'  => $iconRight,
                    'icon'       => $icon,

                    'subNavigation' => $subNavigation,


                ],
                'data'        => PalletsResource::collection($pallets),
            ]
        )->table($this->tableStructure($this->parent, 'pallets'));
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup($this->parent, $request);

        return $this->handle($this->parent);
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment, 'pallets');
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Pallets'),
                        'icon'  => 'fal fa-bars',
                    ],

                ]
            ];
        };

        return match ($routeName) {
            'grp.overview.fulfilment.pallets.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.operations.pallets.current.index',
                        'parameters' => [
                            'organisation' => $routeParameters['organisation'],
                            'fulfilment'   => $routeParameters['fulfilment'],
                        ]
                    ],
                )
            ),
        };
    }
}
