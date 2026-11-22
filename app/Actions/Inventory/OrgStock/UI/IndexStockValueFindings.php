<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Http\Resources\Inventory\StockValueFindingsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\StockValueFinding;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * What the nightly stock check (CheckStockValues) found wrong in this organisation's stock
 * values, with what looks wrong, who acts and what was raised for them (HELP-4868).
 */
class IndexStockValueFindings extends OrgAction
{
    use WithInventoryAuthorisation;

    public static function numberOpen(Organisation $organisation): int
    {
        return StockValueFinding::where('organisation_id', $organisation->id)->where('state', StockValueFinding::STATE_OPEN)->count();
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($organisation);
    }

    protected function stateElements(Organisation $organisation): array
    {
        $counts = StockValueFinding::where('organisation_id', $organisation->id)->toBase()->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state');

        return [
            StockValueFinding::STATE_OPEN     => [__('Open'), (int)($counts[StockValueFinding::STATE_OPEN] ?? 0)],
            StockValueFinding::STATE_RESOLVED => [__('Resolved'), (int)($counts[StockValueFinding::STATE_RESOLVED] ?? 0)],
        ];
    }

    public function handle(Organisation $organisation, string $prefix = 'stock_value_findings'): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($query) => $query->whereStartWith('org_stocks.code', $value)->orWhereAnyWordStartWith('org_stocks.name', $value));
        });

        InertiaTable::updateQueryBuilderParameters($prefix);

        $queryBuilder = QueryBuilder::for(StockValueFinding::class)
            ->where('stock_value_findings.organisation_id', $organisation->id)
            ->leftJoin('org_stocks', 'stock_value_findings.org_stock_id', 'org_stocks.id')
            ->select('stock_value_findings.*', 'org_stocks.code', 'org_stocks.slug', 'org_stocks.name')
            ->with(['location', 'staffTask', 'ticket']);

        $queryBuilder->whereElementGroup(
            key: 'state',
            allowedElements: array_keys($this->stateElements($organisation)),
            engine: fn ($query, $elements) => $query->whereIn('stock_value_findings.state', $elements),
            prefix: $prefix,
            default: StockValueFinding::STATE_OPEN
        );

        return $queryBuilder
            ->defaultSort('-excess_amount')
            ->allowedSorts(['code', 'excess_amount', 'first_seen_at', 'owner'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation $organisation, string $prefix = 'stock_value_findings'): Closure
    {
        return function (InertiaTable $table) use ($organisation, $prefix) {
            $table->name($prefix)->pageName($prefix.'Page');
            $table->elementGroup(key: 'state', label: __('State'), elements: $this->stateElements($organisation), default: StockValueFinding::STATE_OPEN);

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('suspicious value'), __('suspicious values')])
                ->defaultSort('-excess_amount')
                ->column(key: 'code', label: __('SKO'), canBeHidden: false, sortable: true, searchable: true, className: 'whitespace-nowrap w-px')
                ->column(key: 'explanation', label: __('What looks wrong'), canBeHidden: false, className: 'w-full max-w-0')
                ->column(key: 'action', label: __('What to do'), canBeHidden: false, className: 'min-w-[14rem]')
                ->column(key: 'owner', label: __('Who'), canBeHidden: false, sortable: true, className: 'whitespace-nowrap w-px')
                ->column(key: 'excess_amount', label: __('Too much by'), canBeHidden: false, sortable: true, align: 'right', className: 'whitespace-nowrap w-px')
                ->column(key: 'raised', label: __('Raised'), canBeHidden: false, className: 'whitespace-nowrap w-px')
                ->column(key: 'first_seen_at', label: __('Found on'), canBeHidden: false, sortable: true, type: 'date', className: 'whitespace-nowrap w-px');
        };
    }

    public function jsonResponse(LengthAwarePaginator $findings): AnonymousResourceCollection
    {
        return StockValueFindingsResource::collection($findings);
    }

    public function htmlResponse(LengthAwarePaginator $findings, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Inventory/StockValueFindings',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Suspicious stock values'),
                'pageHead'    => [
                    'title'      => __('Suspicious stock values'),
                    'icon'       => [
                        'icon'  => ['fal', 'fa-search-dollar'],
                        'title' => __('Suspicious stock values'),
                    ],
                    'afterTitle' => [
                        'label' => __('Checked every night. A line goes away by itself once the stock is right'),
                    ],
                ],
                'findings'    => StockValueFindingsResource::collection($findings),
                'currency'    => $this->organisation->currency->code,
            ]
        )->table($this->tableStructure($this->organisation));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.inventory.org_stocks.suspicious_values.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Suspicious stock values'),
                        'icon'  => 'fal fa-search-dollar',
                    ],
                ],
            ]
        );
    }
}
