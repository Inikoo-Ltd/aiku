<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnitFamily\UI;

use App\Actions\Goods\TradeUnit\UI\ShowTradeUnitsDashboard;
use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\UI\Goods\TradeUnitFamiliesTabsEnum;
use App\Http\Resources\Goods\TradeUnitFamiliesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\TradeUnitFamily;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexTradeUnitFamilies extends GrpAction
{
    use WithGoodsAuthorisation;

    private Group $parent;

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisation($this->parent, $request)->withTab(TradeUnitFamiliesTabsEnum::values());

        return $this->handle(prefix: TradeUnitFamiliesTabsEnum::INDEX->value);
    }

    public function handle($prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('trade_unit_families.code', $value)
                    ->orWhereAnyWordStartWith('trade_unit_families.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(TradeUnitFamily::class);
        $queryBuilder->where('trade_unit_families.group_id', $this->group->id);
        $queryBuilder->leftjoin('trade_unit_family_stats', 'trade_unit_family_stats.trade_unit_family_id', 'trade_unit_families.id');

        $selects = [
            'trade_unit_families.code',
            'trade_unit_families.slug',
            'trade_unit_families.name',
            'trade_unit_families.description',
            'trade_unit_families.id',
            'trade_unit_family_stats.number_trade_units',
            'trade_unit_family_stats.number_trade_units_status_in_process',
            'trade_unit_family_stats.number_trade_units_status_active',
            'trade_unit_family_stats.number_trade_units_status_discontinued',
            'trade_unit_family_stats.number_trade_units_status_anomality',
        ];

        if ($prefix === TradeUnitFamiliesTabsEnum::SALES->value) {
            $timeSeriesData = $queryBuilder->withTimeSeriesAggregation(
                timeSeriesTable: 'trade_unit_family_time_series',
                timeSeriesRecordsTable: 'trade_unit_family_time_series_records',
                foreignKey: 'trade_unit_family_id',
                aggregateColumns: [
                    'sales_grp_currency_external' => 'sales_grp_currency_external',
                    'invoices'                    => 'invoices',
                ],
                frequency: TimeSeriesFrequencyEnum::DAILY->value,
                prefix: $prefix,
                includeLY: true
            );

            $selects[] = $timeSeriesData['selectRaw']['sales_grp_currency_external'];
            $selects[] = $timeSeriesData['selectRaw']['sales_grp_currency_external_ly'];
            $selects[] = $timeSeriesData['selectRaw']['invoices'];
            $selects[] = $timeSeriesData['selectRaw']['invoices_ly'];
        }

        $allowedSorts = [
            'code',
            'name',
            'number_trade_units_status_in_process',
            'number_trade_units_status_active',
            'number_trade_units_status_discontinued',
            'number_trade_units_status_anomality',
        ];

        if ($prefix === TradeUnitFamiliesTabsEnum::SALES->value) {
            $allowedSorts[] = 'sales_grp_currency_external';
            $allowedSorts[] = 'invoices';
        }

        $queryBuilder
            ->defaultSort('trade_unit_families.code')
            ->select($selects);

        return $queryBuilder->allowedSorts($allowedSorts)
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group $parent, ?array $modelOperations = null, $prefix = null, bool $sales = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $sales) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->defaultSort('code')
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Group' => [
                            'title' => __("No trade unit families found"),
                        ],
                        default => null
                    }
                )
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);

            if ($sales) {
                $table->betweenDates(['date'])
                    ->column(key: 'sales_grp_currency_external', label: __('Sales'), canBeHidden: false, sortable: true, align: 'right')
                    ->column(key: 'sales_grp_currency_external_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, align: 'right')
                    ->column(key: 'invoices', label: __('Invoices'), canBeHidden: false, sortable: true, align: 'right')
                    ->column(key: 'invoices_delta', label: __('Δ 1Y'), canBeHidden: false, sortable: false, align: 'right');
            } else {
                $table
                    ->column(key: 'number_trade_units_status_active', label: __('Active'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'number_trade_units_status_discontinued', label: __('Discontinued'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'number_trade_units_status_anomality', label: __('Anomality'), canBeHidden: false, sortable: true, searchable: true);
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $tradeUnitFamilies): AnonymousResourceCollection
    {
        return TradeUnitFamiliesResource::collection($tradeUnitFamilies);
    }

    public function htmlResponse(LengthAwarePaginator $tradeUnitFamilies, ActionRequest $request): Response
    {
        $actions   = [];
        $actions[] = [
            'type'    => 'button',
            'style'   => 'create',
            'tooltip' => __('New trade unit family'),
            'label'   => __('Trade unit family'),
            'route'   => [
                'name'       => preg_replace('/index$/', 'create', $request->route()->getName()),
                'parameters' => []
            ],
            'method'  => 'get'
        ];

        return Inertia::render(
            'Goods/TradeUnitsFamilies',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Trade Unit Families'),
                'pageHead'    => [
                    'title'     => __('Trade Unit Families'),
                    'actions'   => $actions,
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-atom-alt'],
                        'title' => __('Trade Unit Families'),
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => TradeUnitFamiliesTabsEnum::navigation(),
                ],

                TradeUnitFamiliesTabsEnum::INDEX->value => $this->tab == TradeUnitFamiliesTabsEnum::INDEX->value
                    ? fn () => TradeUnitFamiliesResource::collection($tradeUnitFamilies)
                    : Inertia::lazy(fn () => TradeUnitFamiliesResource::collection($tradeUnitFamilies)),

                TradeUnitFamiliesTabsEnum::SALES->value => $this->tab == TradeUnitFamiliesTabsEnum::SALES->value
                    ? fn () => TradeUnitFamiliesResource::collection($this->handle(prefix: TradeUnitFamiliesTabsEnum::SALES->value))
                    : Inertia::lazy(fn () => TradeUnitFamiliesResource::collection($this->handle(prefix: TradeUnitFamiliesTabsEnum::SALES->value))),
            ]
        )->table($this->tableStructure(parent: $this->parent, prefix: TradeUnitFamiliesTabsEnum::INDEX->value))
         ->table($this->tableStructure(parent: $this->parent, prefix: TradeUnitFamiliesTabsEnum::SALES->value, sales: true));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Trade Unit Families'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return array_merge(
            ShowTradeUnitsDashboard::make()->getBreadcrumbs(),
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
