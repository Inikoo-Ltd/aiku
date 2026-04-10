<?php

namespace App\Actions\CRM\Customer\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\Http\Resources\CRM\CustomerCountriesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Support\Facades\DB;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexCustomerCountries extends OrgAction
{
    use WithCRMAuthorisation;
    use WithCustomersSubNavigation;

    private Shop $parent;

    public function handle(Shop $parent, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Customer::class)
            ->where('customers.shop_id', $parent->id)
            ->whereNotNull('customers.location')
            ->leftJoin('customer_stats', 'customer_stats.customer_id', '=', 'customers.id')
            ->leftJoin(
                DB::raw("(
                    SELECT DISTINCT mht.model_id AS customer_id
                    FROM model_has_tags mht
                    JOIN tags t ON t.id = mht.tag_id AND t.slug = 'active' AND t.scope = 'system_customer'
                    WHERE mht.model_type = 'Customer'
                ) AS rfm_active"),
                'rfm_active.customer_id',
                '=',
                'customers.id'
            )
            ->leftJoin(
                DB::raw("(
                    SELECT DISTINCT mht.model_id AS customer_id
                    FROM model_has_tags mht
                    JOIN tags t ON t.id = mht.tag_id AND t.slug = 'at-risk' AND t.scope = 'system_customer'
                    WHERE mht.model_type = 'Customer'
                ) AS rfm_at_risk"),
                'rfm_at_risk.customer_id',
                '=',
                'customers.id'
            )
            ->leftJoin(
                DB::raw("(
                    SELECT DISTINCT mht.model_id AS customer_id
                    FROM model_has_tags mht
                    JOIN tags t ON t.id = mht.tag_id AND t.slug = 'lost-customer' AND t.scope = 'system_customer'
                    WHERE mht.model_type = 'Customer'
                ) AS rfm_lost"),
                'rfm_lost.customer_id',
                '=',
                'customers.id'
            )
            ->selectRaw("
                customers.location->>0 as country_code,
                customers.location->>1 as country_name,
                COUNT(*) as total,
                COUNT(CASE WHEN rfm_active.customer_id IS NOT NULL THEN 1 END) as number_active,
                COUNT(CASE WHEN rfm_at_risk.customer_id IS NOT NULL THEN 1 END) as number_losing,
                COUNT(CASE WHEN rfm_lost.customer_id IS NOT NULL THEN 1 END) as number_lost,
                COUNT(CASE WHEN COALESCE(customer_stats.number_orders, 0) > 0 THEN 1 END) as number_ordered,
                COUNT(CASE WHEN COALESCE(customer_stats.number_orders, 0) = 0 THEN 1 END) as number_never_ordered
            ")
            ->groupByRaw("customers.location->>0, customers.location->>1")
            ->withBetweenDates(['registered_at']);

        return $queryBuilder
            ->defaultSort('-total')
            ->allowedSorts(['country_code', 'country_name', 'total', 'number_active', 'number_losing', 'number_lost', 'number_ordered', 'number_never_ordered'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withLabelRecord([__('country'), __('countries')])
                ->betweenDates(['registered_at']);

            $table
                ->column(key: 'country_code', label: __('Country'), canBeHidden: false, sortable: true, searchable: false)
                ->column(key: 'total', label: __('Total'), canBeHidden: false, sortable: true)
                ->column(key: 'number_ordered', label: __('Ordered'), canBeHidden: false, sortable: true)
                ->column(key: 'number_never_ordered', label: __('Never Ordered'), canBeHidden: false, sortable: true)
                ->column(key: 'number_active', label: __('Active'), canBeHidden: false, sortable: true)
                ->column(key: 'number_losing', label: __('Potential Comebacks'), canBeHidden: false, sortable: true)
                ->column(key: 'number_lost', label: __('Dormant'), canBeHidden: false, sortable: true);

            $table->defaultSort('-total');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function jsonResponse(LengthAwarePaginator $countries): AnonymousResourceCollection
    {
        return CustomerCountriesResource::collection($countries);
    }

    public function htmlResponse(LengthAwarePaginator $countries, ActionRequest $request): Response
    {
        $subNavigation = $this->getSubNavigation($request);

        return Inertia::render(
            'Org/Shop/CRM/CustomerCountries',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Countries'),
                'pageHead'    => [
                    'title'         => __('Countries'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-globe'],
                        'title' => __('Countries')
                    ],
                    'subNavigation' => $subNavigation,
                ],
                'data' => CustomerCountriesResource::collection($countries),
            ]
        )->table($this->tableStructure(parent: $this->parent));
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
                            'name'       => 'grp.org.shops.show.crm.countries.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Countries'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ]
        );
    }
}
