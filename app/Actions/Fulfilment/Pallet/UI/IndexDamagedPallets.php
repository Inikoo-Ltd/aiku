<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 17 May 2024 13:09:52 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet\UI;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\Pallet;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\InertiaTable\InertiaTable;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;

class IndexDamagedPallets extends OrgAction
{
    use WithFulfilmentShopAuthorisation;
    use WithPalletsSubNavigation;

    private bool $selectStoredPallets = false;


    public function handle(Fulfilment $fulfilment, $prefix = null): LengthAwarePaginator
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

        $query->where('fulfilment_id', $fulfilment->id);

        $query->where('pallets.status', PalletStatusEnum::INCIDENT);
        $query->where('pallets.state', PalletStateEnum::DAMAGED);

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
                'pallets.incident_report'
            );


        return $query->allowedSorts(['customer_reference', 'reference', 'fulfilment_customer_name'])
            ->allowedFilters([$globalSearch, 'customer_reference', 'reference'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Fulfilment $fulfilment, $prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations, $fulfilment) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $emptyStateData = [
                'icons' => ['fal fa-pallet'],
                'title' => '',
                'count' => $fulfilment->stats->number_pallets
            ];

            $emptyStateData['description'] = __("This shop don't have any damaged pallets");


            $table->withGlobalSearch();

            $table->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);

            $table->column(key: 'customer_reference', label: __("Pallet reference (customer's), notes"), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'incident_report_message', label: __("Description"), canBeHidden: false, sortable: true, searchable: true);
            $table->defaultSort('reference');
        };
    }

    public function jsonResponse(LengthAwarePaginator $pallets): AnonymousResourceCollection
    {
        return PalletsResource::collection($pallets);
    }

    public function htmlResponse(LengthAwarePaginator $pallets, ActionRequest $request): Response
    {
        /** @var Fulfilment $fulfilment */
        $fulfilment = $request->route()->parameter('fulfilment');

        return Inertia::render(
            'Org/Fulfilment/Pallets',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('pallets'),
                'pageHead'    => [
                    'title'         => __('Damaged pallets'),
                    'icon'          => ['fal', 'fa-pallet'],
                    'subNavigation' => $this->getPalletsInWarehouseSubNavigation($fulfilment, $request)

                ],
                'data'        => PalletsResource::collection($pallets),
            ]
        )->table($this->tableStructure($fulfilment, 'pallets'));
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment, 'pallets');
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.fulfilments.show.operations.pallets.damaged.index' =>
            array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.operations.pallets.damaged.index',
                                'parameters' => [
                                    'organisation' => $routeParameters['organisation'],
                                    'fulfilment'   => $routeParameters['fulfilment'],
                                ]
                            ],
                            'label' => __('Damaged pallets'),
                            'icon'  => 'fal fa-bars',
                        ],

                    ]
                ]
            ),
        };
    }
}
