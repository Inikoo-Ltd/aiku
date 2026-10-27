<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Tasks;

use App\Actions\Catalogue\Shop\SalesTarget\GetShopMonthSalesTarget;
use App\Actions\Comms\Mailshot\Filters\FilterDueToReorder;
use App\Actions\Helpers\AI\AskToAi;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Dropshipping\WooCommerceConnectionFailureEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\Catalogue\SalesTargetTip;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use App\Notifications\StaffTaskNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Every morning each open shop gets up to five tasks for its shopkeeper in charge, or the group webmasters when it has none, raised
 * by the Aiku assistant, each with about ten customers or items: customers drifting away, open
 * baskets, the gap to the month's target, products back in stock and, for dropshipping, stores we can no longer reach. The lists come from our own
 * data; the AI only words the suggested messages. The previous task of a kind that nobody started
 * is cancelled, so the queue holds one day of them.
 */
class GenerateDailySalesTasks
{
    use AsAction;

    public string $commandSignature = 'tasks:daily-sales {shops?* : shop slugs, all open shops when empty} {--no-ai : leave the suggested messages out}';

    public const string FALLBACK_DEPARTMENT = 'webmaster';

    public const int LINES = 10;

    public const array KINDS = ['sales_drifting', 'sales_open_baskets', 'sales_target_gap', 'sales_back_in_stock', 'sales_broken_channels'];

    /**
     * Lists that only check in on a merchant. A dropshipping merchant on one of them that somebody
     * picked up is left off all three for this many days; broken stores and back in stock alerts still go out.
     */
    public const array CHECK_IN_KINDS = ['sales_drifting', 'sales_open_baskets', 'sales_target_gap'];

    public const int CHECK_IN_COOLDOWN_DAYS = 30;

    /**
     * Campaigns a customer can be told about, and only offers with an end date: volume discounts, step offers,
     * one customer's own deals and open-ended offers are always on and say nothing new.
     */
    public const array PROMOTION_CAMPAIGNS = ['category-offers', 'gift', 'vouchers', 'product-offers', 'order-recursion'];

    private bool $withAi = true;

    private bool $isDropshipping = false;

    /**
     * Dropshipping customers already on one of today's lists or checked in with lately, so each gets one follow-up, not three.
     *
     * @var array<int, int>
     */
    private array $alreadyFollowedUp = [];

    /**
     * @var array<int, array<int, int>>
     */
    private array $excludedCustomerIds = [];

    /**
     * @return array<string, string|null> task reference per kind, null when there was nothing to do
     */
    public function handle(Shop $shop, ?Carbon $today = null, bool $withAi = true): array
    {
        $this->withAi         = $withAi;
        $this->isDropshipping = $shop->type === ShopTypeEnum::DROPSHIPPING;
        $this->alreadyFollowedUp = [];
        $today        = ($today ?? now('UTC'))->copy()->startOfDay();

        $assignment = $this->assignment($shop);

        if (!$assignment) {
            return [];
        }

        $assistant = GetAikuAssistant::run($shop->group_id);

        foreach (self::KINDS as $kind) {
            $this->cancelUntouched($shop, $kind);
        }

        $this->markListedToday($this->recentlyListed($shop, self::CHECK_IN_KINDS, 'customer_ids', self::CHECK_IN_COOLDOWN_DAYS));

        $brokenChannels = $this->brokenChannels($shop, $today);
        $openBaskets    = $this->openBaskets($shop, $today);
        $drifting    = $this->driftingCustomers($shop, $today);

        $drafts = [
            'sales_drifting'      => $drifting,
            'sales_open_baskets'  => $openBaskets,
            'sales_target_gap'    => $this->targetGap($shop, $today),
            'sales_back_in_stock' => $this->backInStock($shop),
            'sales_broken_channels' => $brokenChannels,
        ];

        return collect($drafts)->map(fn (?array $draft, string $kind) => $draft ? $this->store($shop, $assistant, $kind, $draft, $today, $assignment)->reference : null)->all();
    }

    /**
     * Repeat customers whose next order is later than one of their usual intervals, the ones worth
     * most first, leaving out those already listed this month.
     */
    private function driftingCustomers(Shop $shop, Carbon $today): ?array
    {
        $alreadyListed = $this->recentlyListed($shop, 'sales_drifting', 'customer_ids', 30);

        $customers = DB::table('customers')
            ->join('customer_stats', 'customer_stats.customer_id', '=', 'customers.id')
            ->where('customers.shop_id', $shop->id)
            ->whereNull('customers.deleted_at')
            ->where('customers.status', CustomerStatusEnum::APPROVED->value)
            ->where('customer_stats.number_invoices_type_invoice', '>=', 3)
            ->where('customer_stats.last_invoiced_at', '>=', $today->copy()->subMonths(18))
            ->whereRaw("customer_stats.expected_date_of_next_order < ?::timestamptz - customer_stats.average_time_between_orders * interval '1 day'", [$today])
            ->whereNotIn('customers.id', $alreadyListed)
            ->whereNotIn('customers.id', $this->excludedCustomerIds($shop))
            ->whereNotIn('customers.id', $this->alreadyFollowedUp)
            ->whereNotExists(fn ($query) => $query->from('orders')
                ->whereColumn('orders.customer_id', 'customers.id')
                ->whereNull('orders.deleted_at')
                ->whereNotIn('orders.state', [OrderStateEnum::DISPATCHED->value, OrderStateEnum::FINALISED->value, OrderStateEnum::CANCELLED->value]))
            ->orderByDesc('customer_stats.sales_org_currency_all')
            ->limit(self::LINES)
            ->get(['customers.id', 'customers.slug', 'customers.reference', 'customers.name', 'customers.contact_name', 'customer_stats.last_invoiced_at', 'customer_stats.average_time_between_orders', 'customer_stats.number_invoices_type_invoice']);

        if ($customers->isEmpty()) {
            return null;
        }

        $this->markListedToday($customers->pluck('id')->all());

        $facts = $customers->mapWithKeys(fn ($customer) => [$customer->id => $this->isDropshipping ? [
            'customer'       => $this->customerName($customer),
            'contact_person' => $customer->contact_name,
        ] : [
            'customer'              => $this->customerName($customer),
            'contact_person'        => $customer->contact_name,
            'last_order'            => Carbon::parse($customer->last_invoiced_at)->toDateString(),
            'usually_orders_every'  => round((float) $customer->average_time_between_orders).' days',
            'orders_so_far'         => (int) $customer->number_invoices_type_invoice,
        ]])->all();

        $messages = $this->suggestedMessages($shop, $this->isDropshipping
            ? 'These merchants have been quieter than usual. Write a personal note asking how their store is going, whether they are happy with our service, and whether they would like advice on products that sell well, without asking them to order.'
            : 'These are good repeat customers who have not ordered for longer than usual. Write a friendly note checking in on them and inviting them back, without offering any discount.', $facts);

        return [
            'subject'     => __('Customers drifting away in :shop', ['shop' => $shop->name]),
            'intro'       => $this->isDropshipping
                ? __('These merchants have been quieter than usual. Their orders follow their own customers\' demand, so ask how their store is going and whether they need help, not for an order. The suggested message is a starting point.')
                : __('These customers usually order regularly but have gone quiet for longer than usual. Get in touch, the suggested message is a starting point.'),
            'lines'       => $customers->map(fn ($customer) => [
                'title'   => $this->customerName($customer).' ('.$customer->reference.') · '.__('last order :date, usually every :days days', ['date' => Carbon::parse($customer->last_invoiced_at)->toDateString(), 'days' => round((float) $customer->average_time_between_orders)]),
                'url'     => $this->customerUrl($shop, $customer->slug),
                'message' => $messages[$customer->id] ?? null,
            ])->all(),
            'data'        => ['customer_ids' => $customers->pluck('id')->all()],
            'model_type'  => 'Customer',
            'model_id'    => $customers->first()->id,
        ];
    }

    /**
     * Dropshipping stores we can no longer reach while their merchant still sends us orders through
     * them, so their customers' orders may not be getting to us. The merchants worth most first.
     */
    private function brokenChannels(Shop $shop, Carbon $today): ?array
    {
        if (!$this->isDropshipping) {
            return null;
        }

        $alreadyListed = $this->recentlyListed($shop, 'sales_broken_channels', 'customer_sales_channel_ids', 7);

        $channels = DB::table('customer_sales_channels')
            ->join('customers', 'customers.id', '=', 'customer_sales_channels.customer_id')
            ->join('customer_stats', 'customer_stats.customer_id', '=', 'customers.id')
            ->join('platforms', 'platforms.id', '=', 'customer_sales_channels.platform_id')
            ->where('customer_sales_channels.shop_id', $shop->id)
            ->where('customer_sales_channels.status', CustomerSalesChannelStatusEnum::OPEN->value)
            ->whereNull('customer_sales_channels.deleted_at')
            ->where('platforms.type', '!=', PlatformTypeEnum::MANUAL->value)
            ->where(fn ($query) => $query->where('customer_sales_channels.can_connect_to_platform', false)->orWhere('customer_sales_channels.is_down', true))
            ->where('customer_sales_channels.last_order_created_at', '>=', $today->copy()->subDays(90))
            ->whereNull('customers.deleted_at')
            ->whereNotIn('customer_sales_channels.id', $alreadyListed)
            ->whereNotIn('customers.id', $this->excludedCustomerIds($shop))
            ->orderByDesc('customer_stats.sales_org_currency_all')
            ->limit(self::LINES)
            ->get(['customer_sales_channels.id', 'customer_sales_channels.customer_id', 'customer_sales_channels.name as store', 'customer_sales_channels.connection_failure', 'customer_sales_channels.last_order_created_at', 'platforms.name as platform', 'customers.slug', 'customers.reference', 'customers.name', 'customers.contact_name']);

        if ($channels->isEmpty()) {
            return null;
        }

        $this->markListedToday($channels->pluck('customer_id')->all());

        $failure = fn ($channel) => $channel->connection_failure ? WooCommerceConnectionFailureEnum::labels()[$channel->connection_failure] ?? null : null;

        $facts = $channels->mapWithKeys(fn ($channel) => [$channel->id => [
            'customer'       => $this->customerName($channel),
            'contact_person' => $channel->contact_name,
            'store'          => $channel->store,
            'platform'       => $channel->platform,
            'problem_seen'   => $failure($channel),
        ]])->all();

        $messages = $this->suggestedMessages($shop, 'We can no longer connect to these merchants\' online stores, so orders from their customers may not be reaching us. Write a short helpful note telling them we noticed their store stopped syncing with us and offering to help reconnect it; when a problem is given, say in plain words what usually fixes it (for example letting us through their firewall or entering their API keys again).', $facts);

        return [
            'subject'    => __('Stores no longer connected in :shop', ['shop' => $shop->name]),
            'intro'      => __('We cannot reach these merchants\' stores, so their customers\' orders may not be getting to us. Help them reconnect, it is the most useful call today.'),
            'lines'      => $channels->map(fn ($channel) => [
                'title'   => collect([$this->customerName($channel).' ('.$channel->reference.')', $channel->platform, $channel->store, $failure($channel)])->filter()->implode(' · '),
                'url'     => $this->customerUrl($shop, $channel->slug),
                'message' => $messages[$channel->id] ?? null,
            ])->all(),
            'data'       => ['customer_sales_channel_ids' => $channels->pluck('id')->all(), 'customer_ids' => $channels->pluck('customer_id')->unique()->values()->all()],
            'model_type' => 'Customer',
            'model_id'   => $channels->first()->customer_id,
        ];
    }

    /**
     * Baskets customers filled and left in the last two weeks, untouched for at least a day, one line per
     * customer with all their open baskets, the biggest first.
     */
    private function openBaskets(Shop $shop, Carbon $today): ?array
    {
        $alreadyListed = $this->recentlyListed($shop, 'sales_open_baskets', 'order_ids', 7);

        $customers = DB::table('orders')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->where('orders.shop_id', $shop->id)
            ->where('orders.state', OrderStateEnum::CREATING->value)
            ->whereNull('orders.deleted_at')
            ->where('orders.net_amount', '>', 0)
            ->whereBetween('orders.updated_by_customer_at', [$today->copy()->subDays(14), $today->copy()->subDay()])
            ->whereNotIn('orders.id', $alreadyListed)
            ->whereNotIn('orders.customer_id', $this->excludedCustomerIds($shop))
            ->whereNotIn('orders.customer_id', $this->alreadyFollowedUp)
            ->orderByDesc('orders.org_net_amount')
            ->get(['orders.id', 'orders.customer_id', 'orders.slug', 'orders.reference', 'orders.net_amount', 'orders.updated_by_customer_at', 'customers.name', 'customers.contact_name', 'customers.slug as customer_slug', 'customers.reference as customer_reference'])
            ->groupBy('customer_id')
            ->take(self::LINES);

        if ($customers->isEmpty()) {
            return null;
        }

        $this->markListedToday($customers->keys()->all());

        $currency = $shop->currency->code;
        $amount   = fn (Collection $orders) => $currency.' '.number_format((float) $orders->sum('net_amount'), 2);
        $facts    = $customers->map(fn (Collection $orders) => $this->isDropshipping ? [
            'customer'       => $this->customerName($orders->first()),
            'contact_person' => $orders->first()->contact_name,
            'open_orders'    => $orders->pluck('reference')->all(),
        ] : [
            'customer'          => $this->customerName($orders->first()),
            'contact_person'    => $orders->first()->contact_name,
            'open_baskets'      => $orders->pluck('reference')->all(),
            'basket_amount'     => $amount($orders),
            'last_touched_on'   => Carbon::parse($orders->max('updated_by_customer_at'))->toDateString(),
        ])->all();

        $messages = $this->suggestedMessages($shop, $this->isDropshipping
            ? 'These merchants have an order left open on our website, some more than one: write one note per merchant covering all of them. It may be a test, a draft, an order waiting for their own customer, or one created by their store integration, so do not assume they abandoned it. Write a short note asking whether anything got in the way, such as a technical problem, a payment difficulty or a shipping question, and offering help.'
            : 'These customers filled a basket on our website and did not check out, some more than one: write one note per customer covering all of them. Write a short helpful note reminding them the basket is waiting and offering help to complete the order, without offering any discount.', $facts);

        $orderUrl = fn ($order) => route('grp.org.shops.show.ordering.orders.show', [$shop->organisation->slug, $shop->slug, $order->slug]);

        return [
            'subject'    => __('Open baskets to follow up in :shop', ['shop' => $shop->name]),
            'intro'      => $this->isDropshipping
                ? __('These merchants have an order left open. It may be a test, a draft, an order waiting for their customer or one from their integration: check whether they hit a technical, payment or shipping problem.')
                : __('These customers left a basket without checking out. A short reminder or a call often gets the order in.'),
            'lines'      => $customers->map(fn (Collection $orders, int $customerId) => [
                'title'   => $this->customerName($orders->first()).' ('.$orders->first()->customer_reference.') · '.$orders->pluck('reference')->implode(', ').' · '.$amount($orders),
                'url'     => $orders->count() === 1 ? $orderUrl($orders->first()) : $this->customerUrl($shop, $orders->first()->customer_slug),
                'detail'  => $orders->count() === 1 ? null : $orders->map(fn ($order) => $order->reference.' · '.$currency.' '.number_format((float) $order->net_amount, 2).' '.$orderUrl($order))->implode("\n"),
                'message' => $messages[$customerId] ?? null,
            ])->values()->all(),
            'data'       => ['order_ids' => $customers->flatten(1)->pluck('id')->all(), 'customer_ids' => $customers->keys()->all()],
            'model_type' => 'Order',
            'model_id'   => $customers->first()->first()->id,
        ];
    }

    /**
     * What is still needed for the month's target, today's tip, the promotions running now, and the
     * customers due to reorder to call first.
     */
    private function targetGap(Shop $shop, Carbon $today): ?array
    {
        $month = GetShopMonthSalesTarget::run($shop, null, $today);
        $gap   = $month['gap'] ?? null;

        if (!($month['target']['amount'] ?? null) || $gap === null || $gap <= 0) {
            return null;
        }

        $amount = $month['currency_code'].' '.number_format($gap, 0);
        $tip    = SalesTargetTip::where('shop_id', $shop->id)->whereNull('invoice_category_id')->where('date', $today->toDateString())->value('tip');

        $promotions = DB::table('offers')
            ->join('offer_campaigns', 'offer_campaigns.id', '=', 'offers.offer_campaign_id')
            ->where('offers.shop_id', $shop->id)
            ->where('offers.state', OfferStateEnum::ACTIVE->value)
            ->whereNull('offers.deleted_at')
            ->whereNull('offers.customer_id')
            ->whereIn('offer_campaigns.type', self::PROMOTION_CAMPAIGNS)
            ->where('offers.end_at', '>=', $today)
            ->orderBy('offers.end_at')
            ->limit(8)
            ->get(['offers.name', 'offers.end_at'])
            ->map(fn ($offer) => '• '.$offer->name.($offer->end_at ? ' ('.__('until :date', ['date' => Carbon::parse($offer->end_at)->toDateString()]).')' : ''));

        $customers = (new FilterDueToReorder())
            ->whereDue(DB::table('customers')->where('customers.shop_id', $shop->id)->whereNull('customers.deleted_at')->whereNotIn('customers.id', $this->excludedCustomerIds($shop))->whereNotIn('customers.id', $this->alreadyFollowedUp))
            ->join('customer_stats', 'customer_stats.customer_id', '=', 'customers.id')
            ->orderByDesc('customer_stats.sales_org_currency_all')
            ->limit(self::LINES)
            ->get(['customers.id', 'customers.slug', 'customers.reference', 'customers.name', 'customers.contact_name', 'customer_stats.expected_date_of_next_order']);

        $facts = $customers->mapWithKeys(fn ($customer) => [$customer->id => $this->isDropshipping ? [
            'customer'           => $this->customerName($customer),
            'contact_person'     => $customer->contact_name,
            'promotions_running' => $promotions->map(fn (string $line) => ltrim($line, '• '))->all(),
        ] : [
            'customer'              => $this->customerName($customer),
            'contact_person'        => $customer->contact_name,
            'next_order_expected'   => Carbon::parse($customer->expected_date_of_next_order)->toDateString(),
            'promotions_running'    => $promotions->map(fn (string $line) => ltrim($line, '• '))->all(),
        ]])->all();

        $messages = $customers->isEmpty() ? [] : $this->suggestedMessages($shop, $this->isDropshipping
            ? 'These are regular merchants worth keeping close. Write a short personal note asking how their store is doing and offering help; if a promotion is running, mention one as something their customers may like, without urging them to order.'
            : 'These customers are due to place their next order about now. Write a short note inviting them to order, mentioning one of the promotions running if any.', $facts);

        $intro = collect([
            __(':amount to go to reach :month target, after what is invoiced and on its way.', ['amount' => $amount, 'month' => $month['month_label'] ?? '']),
            $tip,
            $promotions->isNotEmpty() ? __('Promotions running now:')."\n".$promotions->implode("\n") : null,
            $customers->isNotEmpty() ? ($this->isDropshipping ? __('Regular merchants to check in with:') : __('Customers due to reorder, to call first:')) : null,
        ])->filter()->implode("\n\n");

        return [
            'subject'    => __(':amount to go to reach this month\'s target in :shop', ['amount' => $amount, 'shop' => $shop->name]),
            'intro'      => $intro,
            'lines'      => $customers->map(fn ($customer) => [
                'title'   => $this->isDropshipping
                    ? $this->customerName($customer).' ('.$customer->reference.')'
                    : $this->customerName($customer).' ('.$customer->reference.') · '.__('next order expected :date', ['date' => Carbon::parse($customer->expected_date_of_next_order)->toDateString()]),
                'url'     => $this->customerUrl($shop, $customer->slug),
                'message' => $messages[$customer->id] ?? null,
            ])->all(),
            'data'       => ['customer_ids' => $customers->pluck('id')->all(), 'gap' => $gap],
        ];
    }

    /**
     * Products in stock again with customers still waiting for them, the most awaited first.
     */
    private function backInStock(Shop $shop): ?array
    {
        $products = DB::table('back_in_stock_reminders')
            ->join('products', 'products.id', '=', 'back_in_stock_reminders.product_id')
            ->where('back_in_stock_reminders.shop_id', $shop->id)
            ->where('products.available_quantity', '>', 0)
            ->whereIn('products.state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value])
            ->whereNull('products.deleted_at')
            ->groupBy('products.id', 'products.slug', 'products.code', 'products.name')
            ->orderByRaw('count(*) desc')
            ->limit(self::LINES)
            ->selectRaw('products.id, products.slug, products.code, products.name, count(*) as waiting')
            ->get();

        if ($products->isEmpty()) {
            return null;
        }

        $waiting = DB::table('back_in_stock_reminders')
            ->join('customers', 'customers.id', '=', 'back_in_stock_reminders.customer_id')
            ->whereNotIn('customers.id', $this->excludedCustomerIds($shop))
            ->whereIn('back_in_stock_reminders.product_id', $products->pluck('id'))
            ->where('back_in_stock_reminders.shop_id', $shop->id)
            ->whereNull('customers.deleted_at')
            ->get(['back_in_stock_reminders.product_id', 'customers.name', 'customers.contact_name', 'customers.reference', 'customers.slug'])
            ->groupBy('product_id');

        $facts = $products->mapWithKeys(fn ($product) => [$product->id => [
            'product'           => $product->name,
            'customers_waiting' => (int) $product->waiting,
        ]])->all();

        $messages = $this->suggestedMessages($shop, $this->isDropshipping
            ? 'These products are back in stock and merchants asked to be told. Write a short email telling a merchant the product they wanted is back, so they can offer it in their store again.'
            : 'These products are back in stock and customers asked to be told. Write a short email telling a customer the product they wanted is back and inviting them to order before it runs out again.', $facts);

        return [
            'subject'    => __('Back in stock: customers to tell in :shop', ['shop' => $shop->name]),
            'intro'      => __('These products are back in stock and these customers asked to hear when they were. Email them.'),
            'lines'      => $products->map(fn ($product) => [
                'title'   => $product->code.' · '.trans_choice(':count customer waiting|:count customers waiting', (int) $product->waiting, ['count' => $product->waiting]),
                'url'     => route('grp.org.shops.show.catalogue.products.current_products.show', [$shop->organisation->slug, $shop->slug, $product->slug]),
                'detail'  => collect($waiting->get($product->id, []))->take(15)->map(fn ($customer) => $this->customerName($customer).' ('.$customer->reference.') '.$this->customerUrl($shop, $customer->slug))->implode("\n"),
                'message' => $messages[$product->id] ?? null,
            ])->all(),
            'data'       => ['product_ids' => $products->pluck('id')->all()],
            'model_type' => 'Product',
            'model_id'   => $products->first()->id,
        ];
    }

    /**
     * @param array<string, mixed> $draft
     */
    private function store(Shop $shop, User $assistant, string $kind, array $draft, Carbon $today, array $assignment): StaffTask
    {
        $description = $draft['intro']."\n\n".collect($draft['lines'])->map(fn (array $line) => collect([
            '• '.$line['title'],
            $line['url'],
            $line['detail'] ?? null,
            $line['message'] ? __('Suggested message:')."\n".$line['message'] : null,
        ])->filter()->implode("\n"))->implode("\n\n");

        return StoreStaffTask::run($assistant, [
            'subject'           => Str::limit($draft['subject'], 250),
            'description'       => $description,
            ...$assignment,
            'model_type'        => $draft['model_type'] ?? null,
            'model_id'          => $draft['model_id'] ?? null,
            'subtasks'          => collect($draft['lines'])->map(fn (array $line) => ['title' => mb_substr($line['title'], 0, 255), 'status' => 'todo'])->all(),
            'notify'            => false,
            'data'              => [
                ...$draft['data'],
                'kind'            => $kind,
                'shop_id'         => $shop->id,
                'date'            => $today->toDateString(),
            ],
        ]);
    }

    /**
     * The shop's shopkeeper in charge does them; a shop without one goes to the group webmasters.
     *
     * @return array{assignee_id?: int, department?: string}|null
     */
    private function assignment(Shop $shop): ?array
    {
        $shopkeeper = User::where('id', data_get($shop->settings, 'catalog.shopkeeper_in_charge_id'))->where('status', true)->first();

        if ($shopkeeper && StaffTask::canBeAssigned($shopkeeper)) {
            return ['assignee_id' => $shopkeeper->id];
        }

        return in_array(self::FALLBACK_DEPARTMENT, array_column(StaffTask::departments($shop->group_id), 'value'), true) ? ['department' => self::FALLBACK_DEPARTMENT] : null;
    }

    /**
     * The previous task of the same kind that nobody picked up makes way for today's. One someone
     * started stays theirs to finish.
     */
    private function cancelUntouched(Shop $shop, string $kind): void
    {
        StaffTask::query()
            ->where('group_id', $shop->group_id)
            ->where('status', StaffTaskStatusEnum::TODO)
            ->where('data->kind', $kind)
            ->where('data->shop_id', $shop->id)
            ->get()
            ->each(fn (StaffTask $task) => $task->update(['status' => StaffTaskStatusEnum::CANCELLED, 'closed_at' => now()]));
    }

    /**
     * @return array<int, int>
     */
    private function recentlyListed(Shop $shop, string|array $kind, string $key, int $days): array
    {
        return StaffTask::query()
            ->where('group_id', $shop->group_id)
            ->whereIn('data->kind', (array) $kind)
            ->where('data->shop_id', $shop->id)
            ->where('status', '!=', StaffTaskStatusEnum::CANCELLED)
            ->where('created_at', '>=', now()->subDays($days))
            ->pluck('data')
            ->flatMap(fn (array $data) => $data[$key] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * One AI call per task writes a message per line in the shop's language, from the facts given only.
     *
     * @param array<int, array<string, mixed>> $facts keyed by line id
     *
     * @return array<int, string>
     */
    private function suggestedMessages(Shop $shop, string $brief, array $facts): array
    {
        if (!$this->withAi || !$facts) {
            return [];
        }

        $language = $shop->language?->name ?? 'English';
        $business = $this->isDropshipping
            ? 'a dropshipping supplier. Its customers are online merchants who sell our products in their own stores and order from us only when their own customers buy, so they never restock and are never due an order'
            : 'a wholesale business';
        $prompt   = "You help the team running {$shop->name}, $business, write to its customers. $brief\n\n"
            .($this->isDropshipping ? 'Never mention order intervals, order counts, amounts, sales targets, restocking or a next order; no urgency or pressure to buy, no discounts. Sound like a person who wants their store to do well over the long run. ' : '')
            ."Write each message in $language, at most three short sentences, signed by nobody, no placeholders, no subject line. "
            ."Greet the contact person by first name when there is one, otherwise the business. Make each message read differently and fit that customer's facts. "
            ."Use only the facts given; never invent products, prices or discounts.\n\n"
            ."Facts, keyed by id:\n".json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n"
            .'Answer with JSON only: an object mapping each id to its message.';

        $answer = AskToAi::run($prompt, config('marketing.sales_target_tip_model'));

        $decoded = json_decode(trim(preg_replace('/^```(?:json)?|```$/m', '', (string) $answer)), true);

        return collect(is_array($decoded) ? $decoded : [])
            ->filter(fn ($message, $id) => is_string($message) && isset($facts[(int) $id]))
            ->mapWithKeys(fn (string $message, $id) => [(int) $id => trim($message)])
            ->all();
    }

    /**
     * Customers that are really us. Our own organisations buying from us are partners, some only
     * as plain accounts under an older company name, so a name starting like one of our
     * organisations (legal suffix dropped) counts too. Staff test accounts use an email on one of
     * our own domains (those of our shops and companies) or the email of an aiku user.
     */
    /**
     * @return array<int, int>
     */
    private function excludedCustomerIds(Shop $shop): array
    {
        return $this->excludedCustomerIds[$shop->id] ??= $this->excludedCustomersQuery($shop)->pluck('customer_id')->map(fn ($id) => (int) $id)->all();
    }

    private function excludedCustomersQuery(Shop $shop): \Illuminate\Database\Query\Builder
    {
        $ourDomains = "select lower(split_part(email::text, '@', 2)) from shops where email is not null
            union select lower(split_part(email::text, '@', 2)) from organisations where email is not null and type = 'shop'";

        // ponytail: name prefix match, a customer really called "AW Artisan ..." would be skipped too; a flag on the customer if that ever happens
        return DB::table('org_partners')->whereNotNull('customer_id')->select('customer_id')
            ->union(DB::table('customers as own')
                ->join('organisations', fn ($join) => $join->whereRaw("own.name ilike regexp_replace(organisations.name, '\\s+(ltd|limited|s\\.r\\.o|s\\.l|sarl)\\.?$', '', 'i') || '%'"))
                ->where('own.shop_id', $shop->id)->select('own.id'))
            ->union(DB::table('customers as staff')
                ->where('staff.shop_id', $shop->id)
                ->whereNotNull('staff.email')
                ->where(fn ($query) => $query
                    ->whereRaw("lower(split_part(staff.email::text, '@', 2)) in ($ourDomains)")
                    ->orWhereRaw('lower(staff.email::text) in (select lower(email::text) from users where email is not null)'))
                ->select('staff.id'));
    }

    /**
     * @param array<int, int> $customerIds
     */
    private function markListedToday(array $customerIds): void
    {
        if ($this->isDropshipping) {
            $this->alreadyFollowedUp = [...$this->alreadyFollowedUp, ...array_map('intval', $customerIds)];
        }
    }

    private function customerName(object $customer): string
    {
        return $customer->name ?: ($customer->contact_name ?: '');
    }

    private function customerUrl(Shop $shop, string $slug): string
    {
        return route('grp.org.shops.show.crm.customers.show', [$shop->organisation->slug, $shop->slug, $slug]);
    }

    /**
     * @return Collection<int, Shop>
     */
    private function shops(array $slugs): Collection
    {
        return Shop::where('state', ShopStateEnum::OPEN)
            ->whereIn('type', [ShopTypeEnum::B2B, ShopTypeEnum::DROPSHIPPING])
            ->when($slugs, fn ($query) => $query->whereIn('slug', $slugs))
            ->get();
    }

    /**
     * One notification per person in the morning, however many shops and tasks they got.
     *
     * @param array<int, string> $references
     */
    public function notifyMorning(array $references): int
    {
        $tasks = StaffTask::whereIn('reference', $references)->with(['assignee', 'requester'])->get();

        $recipients = [];
        foreach ($tasks as $task) {
            $people = $task->assignee ? collect([$task->assignee]) : StaffTask::departmentMembers($task->requester, $task->department);
            foreach ($people->filter(fn (User $user) => StaffTask::canBeAssigned($user)) as $user) {
                $recipients[$user->id] ??= ['user' => $user, 'tasks' => collect()];
                $recipients[$user->id]['tasks']->push($task);
            }
        }

        foreach ($recipients as ['user' => $user, 'tasks' => $userTasks]) {
            $shops = Shop::whereIn('id', $userTasks->map(fn (StaffTask $task) => $task->data['shop_id'])->unique())->pluck('name')->implode(', ');
            Notification::send($user, new StaffTaskNotification(
                $userTasks->first(),
                trans_choice(':count sales task for today|:count sales tasks for today', $userTasks->count(), ['count' => $userTasks->count()]),
                __('Customers to contact and the month target in :shops', ['shops' => $shops]),
                route('grp.tasks.index')
            ));
        }
        SendStaffTaskBadgeUpdateToUsers::run(array_keys($recipients));

        return count($recipients);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $references = [];
        foreach ($this->shops($command->argument('shops')) as $shop) {
            $created    = array_filter($this->handle($shop, withAi: !$command->option('no-ai')));
            $references = [...$references, ...array_values($created)];
            $command->info("$shop->slug: ".($created ? implode(', ', $created) : 'nothing to do'));
        }

        $command->info('notified '.$this->notifyMorning($references).' people');

        return 0;
    }
}
