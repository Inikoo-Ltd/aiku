<?php

namespace App\Actions\Ordering\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexTopCountriesInShop extends OrgAction
{
    use WithOrderingAuthorisation;

    public function handle(Shop $shop, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $dateRange = request()->input('between.date');
        $fromDate  = null;
        $toDate    = null;

        if ($dateRange) {
            $parts = explode('-', $dateRange);
            if (count($parts) === 2) {
                $fromDate = Carbon::createFromFormat('Ymd', $parts[0])->startOfDay();
                $toDate   = Carbon::createFromFormat('Ymd', $parts[1])->endOfDay();
            }
        }

        return QueryBuilder::for(Country::class)
            ->select(
                'countries.id',
                'countries.code',
                'countries.name',
                DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
                DB::raw('COALESCE(SUM(orders.total_amount), 0) as total_amount'),
                DB::raw('COALESCE(AVG(orders.total_amount), 0) as avg_order_amount'),
                DB::raw("'" . $shop->currency->code . "' as currency_code")
            )
            ->join('orders', function ($join) use ($shop) {
                $join->on('orders.delivery_country_id', '=', 'countries.id')
                    ->where('orders.shop_id', '=', $shop->id)
                    ->whereNull('orders.deleted_at');
            })
            ->when($fromDate, fn ($q) => $q->where('orders.date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('orders.date', '<=', $toDate))
            ->defaultSort('-total_orders')
            ->groupBy('countries.id', 'countries.code', 'countries.name')
            ->allowedSorts(['total_orders', 'total_amount', 'countries.name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withLabelRecord([__('country'), __('countries')])
                ->withEmptyState([
                    'title'       => __('No countries found'),
                    'description' => __('No dispatched orders with country data recorded for this shop.'),
                ])
                ->betweenDates(['date'])
                ->column(key: 'name', label: __('Country'), sortable: true)
                ->column(key: 'total_orders', label: __('Orders'), sortable: true)
                ->column(key: 'total_amount', label: __('Total Amount'), sortable: true)
                ->column(key: 'avg_order_amount', label: __('Avg Order'))
                ->defaultSort('-total_orders');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(LengthAwarePaginator $countries, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Ordering/Countries',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Countries'),
                'pageHead'    => [
                    'title' => __('Top Countries Dispatched'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-globe'],
                        'title' => __('Countries'),
                    ],
                ],
                'data' => $countries,
            ]
        )->table($this->tableStructure());
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
                            'name'       => 'grp.org.shops.show.ordering.countries.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Countries'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ]
        );
    }
}
