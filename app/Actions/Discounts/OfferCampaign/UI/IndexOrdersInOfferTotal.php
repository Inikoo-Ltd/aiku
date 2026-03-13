<?php

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\CRM\Customer\UI\WithCustomerSubNavigation;
use App\Actions\Ordering\Order\WithOrdersSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingAuthorisation;
use App\Http\Resources\Sales\OrderResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Discounts\OfferCampaign;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrdersInOfferTotal extends OrgAction
{
    use WithOrderingAuthorisation;
    use WithCustomerSubNavigation;
    use WithOrdersSubNavigation;
    private Organisation|Shop $parent;

    public function handle(Shop $parent, ?OfferCampaign $offerCampaign = null, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('orders.reference', $value)
                    ->orWhereWith('orders.tracking_number', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Order::class);
        $query->where('orders.shop_id', $parent->id);
        $query->whereExists(function ($q) {
            $q->selectRaw(1)
                ->from('transaction_has_offer_allowances')
                ->whereColumn(
                    'transaction_has_offer_allowances.order_id',
                    'orders.id'
                );
        });
        $query->leftJoin('customers', 'orders.customer_id', '=', 'customers.id');
        $query->leftJoin('customer_clients', 'orders.customer_client_id', '=', 'customer_clients.id');
        $query->leftJoin('currencies', 'orders.currency_id', '=', 'currencies.id');
        $query->leftJoin('organisations', 'orders.organisation_id', '=', 'organisations.id');
        $query->leftJoin('shops', function ($join) {
            $join->on('orders.shop_id', '=', 'shops.id')
                ->where('shops.state', ShopStateEnum::OPEN);
        });

        // dd($query->toSql(), $query->getBindings());

        return $query->defaultSort('-orders.date')
            ->select([
                'orders.id',
                'orders.slug',
                'orders.reference',
                'orders.date',
                'orders.submitted_at',
                'orders.dispatched_at',
                'orders.state',
                'orders.created_at',
                'orders.updated_at',
                'orders.is_premium_dispatch',
                'orders.has_extra_packing',
                'orders.has_insurance',
                'orders.slug',
                'orders.net_amount',
                'orders.total_amount',
                'orders.payment_amount',
                'orders.pay_detailed_status',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'customer_clients.name as client_name',
                'customer_clients.ulid as client_ulid',
                'currencies.code as currency_code',
                'currencies.id as currency_id',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'customers.slug as customer_slug',
                'customers.name as customer_name',
                'orders.customer_notes',
                'orders.internal_notes',
                'orders.public_notes',
                'orders.shipping_notes',
                'orders.to_be_paid_by',
                'orders.tracking_number',
                'orders.shipping_data',
                'orders.with_replacement',
            ])
            ->leftJoin('order_stats', 'orders.id', 'order_stats.order_id')
            ->allowedSorts(['id', 'reference', 'date', 'net_amount', 'customer_name', 'pay_detailed_status']) // Ensure `id` is the first sort column
            ->withBetweenDates(['date'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($prefix) {
                InertiaTable::updateQueryBuilderParameters($prefix);
            }


            $noResults = __("No orders found");

            $table->betweenDates(['date']);

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('order'), __('orders')])
                ->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $stats->number_orders ?? 0
                    ]
                );

            $table->column(key: 'state', label: '', type: 'icon');
            $table->column(key: 'reference', label: __('Reference'), sortable: true);

            // if ($bucket == 'dispatched' || $bucket == 'dispatched_today') {
            //     $table->column(key: 'dispatched_at', label: __('Dispatched'), sortable: true, type: 'date_hm');
            // } elseif (!in_array(
            //     $bucket,
            //     [
            //         'in_basket',
            //         'creating',
            //         'all'
            //     ]
            // )) {
            //     $table->column(key: 'submitted_at', label: __('Submitted'), sortable: true, type: 'date_hm');
            // } else {
            //     $table->column(key: 'date', label: __('Created date'), sortable: true, type: 'date');
            // }


            // if ($parent instanceof Shop || $parent instanceof Organisation || $parent instanceof Group) {
            //     $table->column(key: 'customer_name', label: __('Customer'), sortable: true);
            // }
            // if ($parent instanceof Organisation || $parent instanceof Group) {
            //     $table->column(key: 'shop_name', label: __('Shop'), sortable: true);
            // }
            // if ($parent instanceof Group) {
            //     $table->column(key: 'organisation_name', label: __('Organisation'), sortable: true);
            // }
            $table->column(key: 'pay_detailed_status', label: __('Payment'), sortable: true);
            $table->column(key: 'delivery', label: __('Delivery'));
            $table->column(key: 'net_amount', label: __('Net'), sortable: true, type: 'currency');
        };
    }

    public function htmlResponse(LengthAwarePaginator $orders, ActionRequest $request): Response
    {
        return Inertia::render(
            'Ordering/OrdersInOffer',
            [
                'breadcrumbs'    => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'          => __('Orders'),
                'pageHead'       => [
                    'title'         => __('Orders'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-shopping-cart'],
                        'title' => __('Orders')
                    ],
                    'model'         => __('Offer Campaign'),
                    'afterTitle'    => [
                        'label' => __('Orders')
                    ],
                    'iconRight'     => [
                        'icon' => 'fal fa-shopping-cart'
                    ],
                ],
                'data'           => OrderResource::collection($orders),
            ]
        )->table($this->tableStructure());
    }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Orders'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            default => []
        };
    }
}
