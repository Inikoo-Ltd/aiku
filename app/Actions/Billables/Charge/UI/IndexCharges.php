<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:21:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Charge\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Http\Resources\Catalogue\ChargesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Billables\Charge;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCharges extends OrgAction
{
    use WithCatalogueAuthorisation;

    private Group|Shop|Organisation $parent;

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->parent);
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle(parent: $organisation);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $shop);
    }


    public function handle(Group|Shop|Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('charges.name', $value)
                    ->orWhereStartWith('charges.slug', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Charge::class);

        $queryBuilder->leftJoin('organisations', 'charges.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'charges.shop_id', '=', 'shops.id')
            ->leftJoin('currencies', 'charges.currency_id', '=', 'currencies.id');

        if (class_basename($parent) == 'Shop') {
            $queryBuilder->where('charges.shop_id', $parent->id);
        } elseif ($parent instanceof Group) {
            $queryBuilder->where('charges.group_id', $parent->id);
        } elseif (class_basename($parent) == 'Organisation') {
            $queryBuilder->where('charges.organisation_id', $parent->id);
        }

        $timeSeriesData = $queryBuilder->withTimeSeriesAggregation(
            timeSeriesTable: 'asset_time_series',
            timeSeriesRecordsTable: 'asset_time_series_records',
            foreignKey: 'asset_id',
            aggregateColumns: [
                'sales_grp_currency_external' => 'sales_grp_currency_external',
                'invoices'                    => 'invoices',
                'customers_invoiced'          => 'customers_invoiced',
            ],
            frequency: TimeSeriesFrequencyEnum::DAILY->value,
            prefix: $prefix,
            includeLY: true,
            localKey: 'asset_id',
        );

        return $queryBuilder
            ->defaultSort('charges.code')
            ->select([
                'charges.slug',
                'charges.code',
                'charges.name',
                'charges.state',
                'charges.description',
                'charges.created_at',
                'charges.updated_at',
                'currencies.code as currency_code',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                $timeSeriesData['selectRaw']['sales_grp_currency_external'],
                $timeSeriesData['selectRaw']['sales_grp_currency_external_ly'],
                $timeSeriesData['selectRaw']['invoices'],
                $timeSeriesData['selectRaw']['customers_invoiced'],
            ])
            ->allowedSorts(['code', 'name', 'shop_code', 'sales_grp_currency_external', 'customers_invoiced', 'invoices'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Shop|Organisation $parent, ?array $modelOperations = null, $prefix = null, $canEdit = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $canEdit) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table->betweenDates(['date']);

            $table
                ->defaultSort('code')
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withLabelRecord([__('charge'),__('charges')])
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Organisation' => [
                            'title'       => __("No charges found"),
                            'description' => $canEdit && $parent->catalogueStats->number_assets_type_charge == 0 ? __('You dont have any charges yet ✨') : '',
                            'count'       => $parent->catalogueStats->number_assets_type_charge,

                        ],
                        'Shop' => [
                            'title'       => __("No charges found"),
                            'description' => $canEdit ? __('You dont have any charges yet ✨')
                                : null,
                            'count'       => $parent->stats->number_assets_type_charge,
                        ],
                        default => null
                    }
                )
                ->column(key: 'state', label: '', canBeHidden: false, type: 'icon');

            if ($parent instanceof Organisation) {
                $table->column(key: 'shop_code', label: __('Shop'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customers_invoiced', label: __('Customers'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'invoices', label: __('Invoices'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'sales_grp_currency_external', label: __('Sales'), canBeHidden: false, sortable: true, searchable: true, align: 'right', type: 'currency')
                ->column(key: 'sales_grp_currency_external_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, align: 'right');

            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('Organisation'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'shop_name', label: __('Shop'), canBeHidden: false, sortable: true, searchable: true);
            }
        };
    }

    public function htmlResponse(LengthAwarePaginator $charges, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Catalogue/Charges',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Charges'),
                'pageHead'    => [
                    'title'   => __('Charges'),
                    'model'   => $this->parent instanceof Shop ? $this->parent->code : '',
                    'icon'    => [
                        'icon'  => ['fal', 'fa-charging-station'],
                        'title' => __('Charges')
                    ],
                    'actions' => [
                        $this->canEdit && $request->route()->getName() == 'grp.org.shops.show.billables.charges.index' ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('New charge'),
                            'label'   => __('Charge'),
                            'route'   => [
                                'name'       => 'grp.org.shops.show.billables.charges.create',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,
                    ]
                ],
                'data'        => ChargesResource::collection($charges),
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
                        'label' => __('Charges'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.billables.charges.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.overview.billables.charges.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),


            default => []
        };
    }

}
