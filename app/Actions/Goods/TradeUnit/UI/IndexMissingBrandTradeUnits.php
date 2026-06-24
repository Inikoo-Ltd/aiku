<?php

namespace App\Actions\Goods\TradeUnit\UI;

use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Actions\Goods\TradeUnit\UI\Traits\WithTradeUnitIndex;
use App\Enums\Goods\TradeUnit\TradeUnitStatusEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\UI\Goods\TradeUnitsTabsEnum;
use App\Http\Resources\Goods\TradeUnitsResource;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\Group;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexMissingBrandTradeUnits extends GrpAction
{
    use WithGoodsAuthorisation;
    use WithTradeUnitIndex;

    private Group $parent;

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisation($this->parent, $request)->withTab(TradeUnitsTabsEnum::values());

        return $this->handle(prefix: TradeUnitsTabsEnum::INDEX->value);
    }

    public function handle(?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = $this->tradeUnitGlobalSearch();

        $this->updateQueryBuilderParametersIfPrefixed($prefix);

        $queryBuilder = $this->baseTradeUnitIndexBuilder();
        $queryBuilder->where('trade_units.group_id', $this->group->id);
        $queryBuilder->leftJoin('trade_unit_stats', 'trade_unit_stats.trade_unit_id', 'trade_units.id');
        $queryBuilder->whereNotExists(function ($q) {
            $q->from('model_has_brands')
                ->whereColumn('model_has_brands.model_id', 'trade_units.id')
                ->where('model_has_brands.model_type', 'TradeUnit');
        });
        $queryBuilder->whereIn('trade_units.status', [TradeUnitStatusEnum::ACTIVE, TradeUnitStatusEnum::IN_PROCESS]);

        $selects = [
            'trade_units.code',
            'trade_units.slug',
            'trade_units.name',
            'trade_units.description',
            'trade_units.type',
            'trade_unit_stats.number_current_stocks',
            'trade_unit_stats.number_current_products',
            'trade_units.id',
        ];

        if ($prefix === TradeUnitsTabsEnum::SALES->value) {
            $timeSeriesData = $queryBuilder->withTimeSeriesAggregation(
                timeSeriesTable: 'trade_unit_time_series',
                timeSeriesRecordsTable: 'trade_unit_time_series_records',
                foreignKey: 'trade_unit_id',
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

        $allowedSorts = ['code', 'type', 'name', 'number_current_stocks', 'number_current_products'];

        if ($prefix === TradeUnitsTabsEnum::SALES->value) {
            $allowedSorts[] = 'sales_grp_currency_external';
            $allowedSorts[] = 'invoices';
        }

        $queryBuilder
            ->defaultSort('trade_units.code')
            ->select($selects);

        return $this->finalizeTradeUnitIndex(
            queryBuilder: $queryBuilder,
            allowedSorts: $allowedSorts,
            globalSearch: $globalSearch,
            prefix: $prefix
        );
    }

    public function tableStructure(Group $parent, ?array $modelOperations = null, $prefix = null, bool $sales = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $sales) {
            $this->setupTradeUnitTable(
                table: $table,
                modelOperations: $modelOperations,
                prefix: $prefix,
                withLabelRecord: true,
                emptyState: ['title' => __('No Trade Units found')]
            );

            if ($sales) {
                $table->betweenDates(['date']);
                $this->addColumnCodeAndName($table);
                $this->addSalesColumns($table);
            } else {
                $this->addColumnCodeAndName($table);
                $this->addColumnNumberCurrentStocks($table);
                $this->addColumnNumberCurrentProducts($table);
                $this->addColumnType($table, 'Unit label');
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $tradeUnits): AnonymousResourceCollection
    {
        return TradeUnitsResource::collection($tradeUnits);
    }

    public function htmlResponse(LengthAwarePaginator $tradeUnits, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/TradeUnits',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Trade units without brand'),
                'pageHead'    => [
                    'title'         => __('Trade units without brand'),
                    'iconRight'     => [
                        'icon'  => ['fal', 'fa-tag'],
                        'title' => __('Trade units without brand'),
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => TradeUnitsTabsEnum::navigation(),
                ],

                TradeUnitsTabsEnum::INDEX->value => $this->tab == TradeUnitsTabsEnum::INDEX->value
                    ? fn () => TradeUnitsResource::collection($tradeUnits)
                    : Inertia::lazy(fn () => TradeUnitsResource::collection($tradeUnits)),

                TradeUnitsTabsEnum::SALES->value => $this->tab == TradeUnitsTabsEnum::SALES->value
                    ? fn () => TradeUnitsResource::collection($this->handle(prefix: TradeUnitsTabsEnum::SALES->value))
                    : Inertia::lazy(fn () => TradeUnitsResource::collection($this->handle(prefix: TradeUnitsTabsEnum::SALES->value))),
            ]
        )->table($this->tableStructure(parent: $this->parent, prefix: TradeUnitsTabsEnum::INDEX->value))
         ->table($this->tableStructure(parent: $this->parent, prefix: TradeUnitsTabsEnum::SALES->value, sales: true));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Without Brand'),
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
