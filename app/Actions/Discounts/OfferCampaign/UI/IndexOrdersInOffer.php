<?php

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Sales\OrderResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrdersInOffer extends OrgAction
{
    protected OfferCampaign $offerCampaign;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("discounts.{$this->shop->id}.view");
    }

    public function asController(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, ActionRequest $request): LengthAwarePaginator
    {
        $this->offerCampaign = $offerCampaign;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offerCampaign);
    }

    public function handle(OfferCampaign $offerCampaign, $prefix = null): LengthAwarePaginator
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

        $query->leftJoin('customers', 'orders.customer_id', '=', 'customers.id');
        $query->leftJoin('customer_clients', 'orders.customer_client_id', '=', 'customer_clients.id');
        $query->leftJoin('currencies', 'orders.currency_id', '=', 'currencies.id');
        $query->leftJoin('organisations', 'orders.organisation_id', '=', 'organisations.id');
        $query->leftJoin('shops', 'orders.shop_id', '=', 'shops.id');
        $query->whereExists(function ($query) use ($offerCampaign) {
            $query->select(DB::raw(1))
                ->from('transaction_has_offer_allowances')
                ->join('invoice_transactions', 'invoice_transactions.transaction_id', '=', 'transaction_has_offer_allowances.transaction_id')
                ->whereColumn('invoice_transactions.order_id', 'orders.id')
                ->where('transaction_has_offer_allowances.offer_campaign_id', $offerCampaign->id);
        });

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
            ->allowedSorts(['id', 'reference', 'date', 'net_amount', 'customer_name', 'pay_detailed_status'])
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

            $table->betweenDates(['date']);

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('order'), __('orders')])
                ->withEmptyState([
                    'title' => __('No orders found'),
                ]);

            $table->column(key: 'state', label: '', type: 'icon');
            $table->column(key: 'reference', label: __('Reference'), sortable: true);
            $table->column(key: 'customer_name', label: __('Customer'), sortable: true);
            $table->column(key: 'date', label: __('Date'), sortable: true, type: 'date');
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
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Orders'),
                'pageHead'    => [
                    'title'      => $this->offerCampaign->name,
                    'model'      => __('Offer Campaign'),
                    'afterTitle' => [
                        'label' => __('Orders'),
                    ],
                    'iconRight'  => [
                        'icon' => 'fal fa-shopping-cart',
                    ],
                    'icon'       => [
                        'icon'  => ['fal', 'fa-shopping-cart'],
                        'title' => __('Orders'),
                    ],
                ],
                'data' => OrderResource::collection($orders),
            ]
        )->table($this->tableStructure());
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
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.discounts.campaigns.orders' => array_merge(
                ShowOfferCampaign::make()->getBreadcrumbs(
                    $this->offerCampaign,
                    'grp.org.shops.show.discounts.campaigns.show',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.discounts.campaigns.orders',
                        'parameters' => $routeParameters,
                    ]
                )
            ),
            default => []
        };
    }
}
