<?php

namespace App\Actions\Goods\TradeUnit\UI;

use App\Actions\GrpAction;
use App\Actions\Goods\TradeUnit\UI\Traits\WithTradeUnitIndex;
use App\Actions\Helpers\Brand\UI\ShowBrand;
use App\Actions\Helpers\Brand\WithBrandSubNavigation;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Enums\UI\Goods\TradeUnitsTabsEnum;
use App\Http\Resources\Goods\TradeUnitsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Brand;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexTradeUnitsInBrand extends GrpAction
{
    use WithGoodsAuthorisation;
    use WithTradeUnitIndex;
    use WithBrandSubNavigation;

    private Brand $brand;

    public function handle(Brand $brand, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = $this->tradeUnitGlobalSearch();

        $this->updateQueryBuilderParametersIfPrefixed($prefix);

        $queryBuilder = $this->baseTradeUnitIndexBuilder();
        $queryBuilder->leftJoin('model_has_brands', function ($join) {
            $join->on('trade_units.id', '=', 'model_has_brands.model_id')
                ->where('model_has_brands.model_type', '=', 'TradeUnit');
        })->where('model_has_brands.brand_id', $brand->id);
        $queryBuilder->leftJoin('trade_unit_stats', 'trade_unit_stats.trade_unit_id', 'trade_units.id');

        $queryBuilder
            ->defaultSort('trade_units.code')
            ->select([
                'trade_units.code',
                'trade_units.slug',
                'trade_units.name',
                'trade_units.description',
                'trade_units.gross_weight',
                'trade_units.marketing_weight',
                'trade_units.volume',
                'trade_units.type',
                'trade_unit_stats.number_current_stocks',
                'trade_unit_stats.number_current_products',
                'trade_units.id',
            ]);

        return $this->finalizeTradeUnitIndex(
            queryBuilder: $queryBuilder,
            allowedSorts: ['code', 'type', 'name', 'number_current_stocks', 'number_current_products', 'marketing_weight'],
            globalSearch: $globalSearch,
            prefix: $prefix
        );
    }

    public function asController(Brand $brand, ActionRequest $request): LengthAwarePaginator
    {
        $this->brand = $brand;
        $this->initialisation(group(), $request)->withTab(TradeUnitsTabsEnum::values());

        return $this->handle($brand, TradeUnitsTabsEnum::INDEX->value);
    }

    public function jsonResponse(LengthAwarePaginator $tradeUnits): AnonymousResourceCollection
    {
        return TradeUnitsResource::collection($tradeUnits);
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            $this->setupTradeUnitTable(
                table: $table,
                modelOperations: $modelOperations,
                prefix: $prefix,
                withLabelRecord: false,
                emptyState: [
                    'title' => __('No trade units found'),
                ]
            );

            $this->addColumnCodeAndName($table);
            $this->addColumnType($table, 'Unit label');
            $this->addColumnNumberCurrentStocks($table);
            $this->addColumnMarketingWeight($table);
        };
    }

    public function htmlResponse(LengthAwarePaginator $tradeUnits, ActionRequest $request): Response
    {
        $title = __('Trade Units').' - '.$this->brand->name;

        return Inertia::render(
            'Goods/TradeUnits',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'actions' => [
                        [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('Add Trade Units under this Brand'),
                            'label'   => __('Add Trade Units'),
                            'key'     => 'add_trade_units',
                            'route'   => [
                                // 'name'       => 'grp.org.shops.show.billables.charges.create',
                                // 'parameters' => $request->route()->originalParameters()
                            ]
                        ]
                    ],
                    'title'         => $this->brand->name,
                    'model'         => __('Trade Units'),
                    'iconRight'     => [
                        'icon'      => ['fal', 'fa-atom'],
                        'title'     => $title,
                    ],
                    'subNavigation' => $this->getBrandSubNavigation($this->brand),
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => TradeUnitsTabsEnum::navigationExcept([TradeUnitsTabsEnum::SALES]),
                ],
                'currentBrand'  => [
                    'slug'  => $this->brand->slug,
                    'id'    => $this->brand->id
                ],

                TradeUnitsTabsEnum::INDEX->value => $this->tab == TradeUnitsTabsEnum::INDEX->value
                    ? fn () => TradeUnitsResource::collection($tradeUnits)
                    : Inertia::lazy(fn () => TradeUnitsResource::collection($tradeUnits)),
            ]
        )->table($this->tableStructure(prefix: TradeUnitsTabsEnum::INDEX->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        return array_merge(
            ShowBrand::make()->getBreadcrumbs($this->brand, 'grp.trade_units.brands.show', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Trade Units'),
                        'icon'  => 'fal fa-bars',
                    ],
                    'suffix' => $suffix,
                ],
            ]
        );
    }
}
