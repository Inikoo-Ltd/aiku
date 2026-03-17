<?php

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\OrgAction;
use App\Http\Resources\CRM\CustomersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCustomersInOffer extends OrgAction
{
    private Shop $parent;
    private OfferCampaign $offerCampaign;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("discounts.{$this->shop->id}.view");
    }

    public function asController(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->offerCampaign = $offerCampaign;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $offerCampaign);
    }

    public function handle(Shop $parent, OfferCampaign $offerCampaign, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('customers.reference', $value)
                    ->orWhereWith('customers.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Customer::class);
        $queryBuilder->where('customers.shop_id', $parent->id);

        $queryBuilder->whereIn('customers.id', function($sub) use ($offerCampaign) {
            $sub->select('orders.customer_id')
                ->from('transaction_has_offer_allowances')
                ->join('orders', 'orders.id', '=', 'transaction_has_offer_allowances.order_id')
                ->where('transaction_has_offer_allowances.offer_campaign_id', $offerCampaign->id)
                ->distinct();
        });
        
        $queryBuilder->with('tags');

        return $queryBuilder
            ->defaultSort('-created_at')
            ->addSelect([
                'customers.location',
                'customers.reference',
                'customers.id',
                'customers.name',
                'customers.slug',
                'customers.created_at',
                'customer_stats.last_invoiced_at',
                'customer_stats.number_invoices_type_invoice',
                'customer_stats.sales_all',
                'shops.currency_id',
                'currencies.code as currency_code',
            ])
            ->leftJoin('customer_stats', 'customers.id', 'customer_stats.customer_id')
            ->leftJoin('shops', 'customers.shop_id', 'shops.id')
            ->leftJoin('currencies', 'shops.currency_id', 'currencies.id')
            ->allowedSorts(['reference', 'name', 'created_at', 'number_invoices_type_invoice', 'last_invoiced_at', 'sales_all'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withLabelRecord([__('customer'), __('customers')])
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No customers found'),
                ])
                ->column(key: 'reference', label: __('Ref'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('Location'), canBeHidden: false, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'created_at', label: __('Since'), canBeHidden: false, sortable: true, searchable: true, type: 'date_hms')
                ->column(key: 'last_invoiced_at', label: __('Last Invoice'), canBeHidden: false, sortable: true, searchable: true, type: 'date')
                ->column(key: 'number_invoices_type_invoice', label: __('Invoices'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'sales_all', label: __('Sales'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'tags', label: __('Tags'), canBeHidden: false)
                ->defaultSort('-created_at');
        };
    }

    public function jsonResponse(LengthAwarePaginator $customers): AnonymousResourceCollection
    {
        return CustomersResource::collection($customers);
    }

    public function htmlResponse(LengthAwarePaginator $customers, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Shop/CRM/Customers',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Customers'),
                'pageHead'    => [
                    'title'      => $this->offerCampaign->name,
                    'model'      => __('offer campaign'),
                    'afterTitle' => [
                        'label' => __('Customers')
                    ],
                    'iconRight'  => [
                        'icon' => 'fal fa-user',
                    ],
                    'icon'       => [
                        'icon'  => ['fal', 'fa-user'],
                        'title' => __('Customers')
                    ],
                ],
                'data'      => CustomersResource::collection($customers),
                'customers' => CustomersResource::collection($customers),
            ]
        )->table($this->tableStructure(parent: $this->parent));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Customers'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.discounts.campaigns.customers' =>
            array_merge(
                ShowOfferCampaign::make()->getBreadcrumbs(
                    $this->offerCampaign,
                    'grp.org.shops.show.discounts.campaigns.show',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.discounts.campaigns.customers',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
