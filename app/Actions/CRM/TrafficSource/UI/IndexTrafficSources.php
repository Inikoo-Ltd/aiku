<?php

namespace App\Actions\CRM\TrafficSource\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Comms\Mailshot\UI\HasUIMailshots;
use App\Actions\Comms\Mailshot\UI\WithIndexMailshots;
use App\Actions\OrgAction;
use App\Http\Resources\CRM\TrafficSourcesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\CRM\TrafficSource;

class IndexTrafficSources extends OrgAction
{
    use HasUIMailshots;
    use WithIndexMailshots;

    private Shop|Organisation $parent;

    public function handle(Shop|Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('traffic_sources.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(TrafficSource::class);

        if ($parent instanceof Organisation) {
            $queryBuilder->where('traffic_sources.organisation_id', $parent->id);
            $queryBuilder->leftJoin('organisations', 'organisations.id', '=', 'traffic_sources.organisation_id');
            $queryBuilder->leftJoin('currencies', 'currencies.id', '=', 'organisations.currency_id');
        } else {
            $queryBuilder->where('traffic_sources.shop_id', $parent->id);
            $queryBuilder->leftJoin('shops', 'shops.id', '=', 'traffic_sources.shop_id');
            $queryBuilder->leftJoin('currencies', 'currencies.id', '=', 'shops.currency_id');
        }

        $queryBuilder->leftJoin('traffic_source_stats', function ($join) {
            $join->on('traffic_sources.id', '=', 'traffic_source_stats.traffic_source_id');
        });


        /* Both figures have to be in the currency this screen labels them with: the organisation-scoped
           listing joins the organisation's currency, the shop-scoped one the shop's. Picking the wrong
           pair would not just mislabel them, it would make the return on ad spend below a ratio between
           two different currencies. */
        $costField    = $parent instanceof Organisation ? 'org_total_cost' : 'total_cost';
        $revenueField = $parent instanceof Organisation ? 'org_total_customer_revenue' : 'total_customer_revenue';

        $selectFields = [
            'traffic_sources.id',
            'traffic_sources.slug',
            'traffic_sources.name',
            /* Both counts are share weighted, so they are stored fractional: a customer credited to two
               channels is half a customer to each. Whole values still have to read as counts rather
               than as money, so the trailing zeros come off and only a genuine fraction keeps them. */
            DB::raw('trim_scale(traffic_source_stats.number_customers) as number_customers'),
            DB::raw('trim_scale(traffic_source_stats.number_customer_purchases) as number_customer_purchases'),
            "traffic_source_stats.{$revenueField} as total_customer_revenue",
            "traffic_source_stats.{$costField} as cost",
            'currencies.code as currency_code',

            /* Guarded against the no-spend case, which is every source until costs are imported and
               permanently the case for organic ones: a null reads as "not applicable" in the table,
               where a zero would read as "this campaign returned nothing". */
            DB::raw("CASE WHEN traffic_source_stats.{$costField} > 0
                        THEN ROUND(traffic_source_stats.{$revenueField} / traffic_source_stats.{$costField}, 2)
                    END as roas"),
            DB::raw("CASE WHEN traffic_source_stats.number_customers > 0 AND traffic_source_stats.{$costField} > 0
                        THEN ROUND(traffic_source_stats.{$costField} / traffic_source_stats.number_customers, 2)
                    END as cac"),
        ];

        $groupByFields = [
            'traffic_sources.id',
            'traffic_source_stats.id',
            'currencies.id'
        ];

        $queryBuilder
            ->defaultSort('traffic_sources.id')
            ->select($selectFields)
            ->groupBy($groupByFields);

        $allowedSorts = [
            'name',
            'number_customers',
            'number_customer_purchases',
            'total_customer_revenue',
            'cost',
            'roas',
            'cac',
        ];

        return $queryBuilder
            ->allowedSorts($allowedSorts)
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(
        Shop|Organisation $parent,
        ?array $modelOperations = null,
        $prefix = null,
    ): Closure {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations);

            $table
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_customers', label: __('Registrations'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'number_customer_purchases', label: __('Orders'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'cost', label: __('Cost'), canBeHidden: false, sortable: true, type: 'currency')
                ->column(key: 'total_customer_revenue', label: __('Revenue'), canBeHidden: false, sortable: true, type: 'currency')
                ->column(key: 'roas', label: __('ROAS'), canBeHidden: true, sortable: true, align: 'right')
                ->column(key: 'cac', label: __('CAC'), canBeHidden: true, sortable: true, type: 'currency');
        };
    }

    public function htmlResponse(LengthAwarePaginator $trafficSources, ActionRequest $request): Response
    {
        $title         = __('Traffic Sources');
        $model         = __('Traffic Source');
        $icon          = [
            'icon'  => ['fal', 'fa-route'],
            'title' => __('Traffic sources')
        ];
        $afterTitle    = null;
        $iconRight     = null;

        if ($this->parent instanceof Shop) {
            $title      = $this->parent->name;
            $model      = __('Traffic Source');
            $icon       = [
                'icon'  => ['fal', 'fa-route'],
                'title' => __('Traffic source')
            ];
        }

        $action = [];


        return Inertia::render(
            'Org/Shop/CRM/TrafficSources',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Traffic Sources'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'actions'       => $action,
                ],
                'data'        => TrafficSourcesResource::collection($trafficSources), // You may want to use a resource if needed
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.marketing.traffic_sources.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Traffic Sources'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ],
        );
    }
}
