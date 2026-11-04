<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteConversionEvent\UI;

use App\Actions\OrgAction;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Enums\Web\WebsiteConversionEvent\WebsiteConversionEventTypeEnum;
use App\Enums\Web\WebsiteVisitor\WebsiteVisitorChannelEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Asset;
use App\Models\CRM\Customer;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Each customer with what they did on the website in the period: products added to the basket,
 * checkouts opened and orders submitted. Checkouts and purchases belong to the customer of their
 * order; an add to basket has no order yet, so it belongs to the customer of the web user signed in.
 * A product code narrows everything to that product: the adds of it, the checkouts and purchases of
 * orders holding it, so the counts and the With / Without filters answer for that product alone.
 */
class IndexWebsiteConversionCustomers extends OrgAction
{
    private const string CUSTOMER_ID = 'COALESCE(orders.customer_id, web_users.customer_id)';

    public function handle(Website $website, ?string $fromDate = null, ?string $toDate = null, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $value = strip_tags($value);
                $query->whereAnyWordStartWith('customers.name', $value)
                    ->orWhereAnyWordStartWith('customers.contact_name', $value)
                    ->orWhereStartWith('customers.email', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Customer::class)
            ->joinSub($this->conversionsByCustomer($website, $fromDate, $toDate, $this->productCode($prefix)), 'conversions', 'conversions.customer_id', '=', 'customers.id')
            ->leftJoin('organisations', 'customers.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'customers.shop_id', '=', 'shops.id');

        foreach ($this->getElementGroups() as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
                optional: true
            );
        }

        return $queryBuilder
            ->defaultSort('-last_event_at')
            ->select([
                'customers.id',
                'customers.slug',
                'customers.name',
                'customers.contact_name',
                'customers.email',
                'organisations.slug as organisation_slug',
                'shops.slug as shop_slug',
                'conversions.add_to_baskets',
                'conversions.checkouts',
                'conversions.purchases',
                'conversions.revenue',
                'conversions.last_event_at',
                'conversions.traffic_source_types',
            ])
            ->allowedSorts(['name', 'add_to_baskets', 'checkouts', 'purchases', 'revenue', 'last_event_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function productCode(?string $prefix): ?string
    {
        $code = trim((string) request()->input(($prefix ? $prefix.'_' : '').'product'));

        return $code === '' ? null : $code;
    }

    public function matchingAssets(Website $website, ?string $productCode): ?EloquentBuilder
    {
        return $productCode === null
            ? null
            : Asset::query()->select('id')->where('shop_id', $website->shop_id)->whereStartWith('code', $productCode);
    }

    private function conversionsByCustomer(Website $website, ?string $fromDate, ?string $toDate, ?string $productCode = null): Builder
    {
        $addToBasket = WebsiteConversionEventTypeEnum::ADD_TO_BASKET->value;
        $checkout    = WebsiteConversionEventTypeEnum::CHECKOUT->value;
        $purchase    = WebsiteConversionEventTypeEnum::PURCHASE->value;

        $matchingAssets = $this->matchingAssets($website, $productCode);

        return DB::table('website_conversion_events')
            ->join('website_visitors', 'website_visitors.id', '=', 'website_conversion_events.website_visitor_id')
            ->leftJoin('orders', 'orders.id', '=', 'website_conversion_events.order_id')
            ->leftJoin('web_users', 'web_users.id', '=', 'website_visitors.web_user_id')
            ->where('website_conversion_events.website_id', $website->id)
            ->whereIn('website_conversion_events.event_type', [$addToBasket, $checkout, $purchase])
            ->whereRaw(self::CUSTOMER_ID.' IS NOT NULL')
            ->when($fromDate, fn ($query) => $query->where('website_conversion_events.event_date', '>=', Carbon::parse($fromDate)->toDateString()))
            ->when($toDate, fn ($query) => $query->where('website_conversion_events.event_date', '<=', Carbon::parse($toDate)->toDateString()))
            ->when($matchingAssets, fn ($query) => $query->where(function ($query) use ($matchingAssets, $addToBasket, $checkout, $purchase) {
                $query
                    ->where(fn ($query) => $query
                        ->where('website_conversion_events.event_type', $addToBasket)
                        ->whereIn('website_conversion_events.product_id', $matchingAssets))
                    ->orWhere(fn ($query) => $query
                        ->whereIn('website_conversion_events.event_type', [$checkout, $purchase])
                        ->whereExists(fn ($transactions) => $transactions
                            ->selectRaw('1')
                            ->from('transactions')
                            ->whereColumn('transactions.order_id', 'website_conversion_events.order_id')
                            ->where('transactions.model_type', 'Product')
                            ->whereNull('transactions.deleted_at')
                            ->whereIn('transactions.asset_id', $matchingAssets)));
            }))
            ->groupByRaw(self::CUSTOMER_ID)
            ->selectRaw(self::CUSTOMER_ID.' as customer_id')
            ->selectRaw("SUM(CASE WHEN website_conversion_events.event_type = '$addToBasket' THEN 1 ELSE 0 END) as add_to_baskets")
            ->selectRaw("SUM(CASE WHEN website_conversion_events.event_type = '$checkout' THEN 1 ELSE 0 END) as checkouts")
            ->selectRaw("SUM(CASE WHEN website_conversion_events.event_type = '$purchase' THEN 1 ELSE 0 END) as purchases")
            ->selectRaw("SUM(CASE WHEN website_conversion_events.event_type = '$purchase' THEN website_conversion_events.net_amount ELSE 0 END) as revenue")
            ->selectRaw('MAX(website_conversion_events.created_at) as last_event_at')
            ->selectRaw('STRING_AGG(DISTINCT website_visitors.traffic_source_type, \',\') as traffic_source_types')
            ->selectRaw('ARRAY_AGG(DISTINCT website_visitors.traffic_source_type) FILTER (WHERE website_visitors.traffic_source_type IS NOT NULL) as traffic_source_type_list');
    }

    /**
     * How many customers of the period came in through each channel, most first; a customer whose
     * visits came through several channels counts once in each.
     *
     * @return array<string, int>
     */
    private function channelCounts(Website $website, ?string $fromDate, ?string $toDate, ?string $productCode): array
    {
        return DB::query()
            ->fromSub($this->conversionsByCustomer($website, $fromDate, $toDate, $productCode), 'conversions')
            ->crossJoin(DB::raw('UNNEST(conversions.traffic_source_type_list) as channel'))
            ->groupBy('channel')
            ->orderByDesc('customers')
            ->selectRaw('channel, COUNT(*) as customers')
            ->pluck('customers', 'channel')
            ->map(fn ($customers) => (int) $customers)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function channelTypes(): array
    {
        return [
            ...array_map(fn (TrafficSourcesTypeEnum $type) => $type->value, TrafficSourcesTypeEnum::cases()),
            WebsiteVisitorChannelEnum::EMAIL_TYPE,
            WebsiteVisitorChannelEnum::INTERNAL_TYPE,
        ];
    }

    /**
     * How many customers of the period each With / Without filter would keep.
     *
     * @return array<string, int>
     */
    private function elementCounts(Website $website, ?string $fromDate, ?string $toDate, ?string $productCode): array
    {
        $counts = DB::query()->fromSub($this->conversionsByCustomer($website, $fromDate, $toDate, $productCode), 'conversions');

        foreach ($this->actionColumns() as $action => $column) {
            $counts
                ->selectRaw("COUNT(*) FILTER (WHERE $column > 0) as with_$action")
                ->selectRaw("COUNT(*) FILTER (WHERE $column = 0) as without_$action");
        }

        return array_map('intval', (array) $counts->first());
    }

    /**
     * @return array<string, string>
     */
    private function actionColumns(): array
    {
        return [
            WebsiteConversionEventTypeEnum::ADD_TO_BASKET->value => 'add_to_baskets',
            WebsiteConversionEventTypeEnum::CHECKOUT->value      => 'checkouts',
            WebsiteConversionEventTypeEnum::PURCHASE->value      => 'purchases',
        ];
    }

    /**
     * With keeps the customers who did at least one of the ticked actions, Without those who did
     * none of them, so With add to basket + Without purchase lists the baskets that never became an order.
     *
     * Channel keeps the customers whose visits came in through at least one of the ticked channels;
     * it lists only the channels seen in the period.
     *
     * @param  array<string, int>  $counts
     * @param  array<string, int>|null  $channelCounts
     * @return array<string, array{label: string, elements: array<string, array{0: string, 1: int}>, engine: Closure}>
     */
    private function getElementGroups(array $counts = [], ?array $channelCounts = null): array
    {
        $labels = [
            WebsiteConversionEventTypeEnum::ADD_TO_BASKET->value => __('Add to basket'),
            WebsiteConversionEventTypeEnum::CHECKOUT->value      => __('Checkout'),
            WebsiteConversionEventTypeEnum::PURCHASE->value      => __('Purchase'),
        ];

        $columns  = $this->actionColumns();
        $elements = fn (string $group) => collect($labels)
            ->map(fn (string $label, string $action) => [$label, $counts[$group.'_'.$action] ?? 0])
            ->all();

        return [
            'with'    => [
                'label'    => __('With'),
                'elements' => $elements('with'),
                'engine'   => function ($query, $elements) use ($columns) {
                    $query->where(function ($query) use ($elements, $columns) {
                        foreach ($elements as $action) {
                            $query->orWhere('conversions.'.$columns[$action], '>', 0);
                        }
                    });
                },
            ],
            'without' => [
                'label'    => __('Without'),
                'elements' => $elements('without'),
                'engine'   => function ($query, $elements) use ($columns) {
                    foreach ($elements as $action) {
                        $query->where('conversions.'.$columns[$action], 0);
                    }
                },
            ],
            'channel' => [
                'label'    => __('Channel'),
                'elements' => collect($channelCounts ?? array_fill_keys($this->channelTypes(), 0))
                    ->map(fn (int $customers, string $type) => [WebsiteVisitorChannelEnum::typeLabel($type), $customers])
                    ->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereRaw('conversions.traffic_source_type_list && ?::varchar[]', ['{'.implode(',', $elements).'}']);
                },
            ],
        ];
    }

    public function tableStructure(Website $website, ?string $fromDate = null, ?string $toDate = null, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($website, $fromDate, $toDate, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $productCode   = $this->productCode($prefix);
            $elementGroups = $this->getElementGroups(
                $this->elementCounts($website, $fromDate, $toDate, $productCode),
                $this->channelCounts($website, $fromDate, $toDate, $productCode)
            );

            foreach ($elementGroups as $key => $elementGroup) {
                if ($elementGroup['elements'] === []) {
                    continue;
                }

                $table->elementGroup(key: $key, label: $elementGroup['label'], elements: $elementGroup['elements'], optional: true);
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('customer'), __('customers')])
                ->withEmptyState([
                    'title'       => __('No customer added to the basket or reached the checkout in this period'),
                    'description' => __('Baskets, checkouts and purchases are recorded from the website, so orders placed by staff or through a sales channel are not listed.'),
                ])
                ->column(key: 'name', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'traffic_source_types', label: __('Channel'), tooltip: __('Where the visits that added to the basket, checked out or purchased came from'), tooltipIcon: true)
                ->column(key: 'add_to_baskets', label: __('Add to basket'), tooltip: __('Products this customer added to the basket while signed in, counted once per add'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'checkouts', label: __('Checkouts'), tooltip: __('Orders this customer opened the checkout for, counted once per order and visit'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'purchases', label: __('Purchases'), tooltip: __('Orders submitted, counted once per order'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'revenue', label: __('Revenue'), tooltip: __('Net amount of the orders submitted, in the shop currency'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'last_event_at', label: __('Last activity'), sortable: true, align: 'right')
                ->defaultSort('-last_event_at');
        };
    }
}
