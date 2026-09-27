<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\CRM\Customer\GetTopCustomersStats;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerTradeStateEnum;
use App\Enums\CRM\Livechat\ChatTopicEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Precomputes the shop dashboard's Customers tab into shop_crm_stats.customers_dashboard, so the
 * tab reads one row instead of querying customers: who is buying, slipping away or gone, the
 * lists worth acting on, and the buyers this shop shares with sister shops (same email).
 */
class ShopHydrateCustomersDashboard implements ShouldBeUnique
{
    use AsAction;

    private const int LIMIT = 10;

    /**
     * Chat topics that mean something went wrong for the customer, as opposed to a question.
     */
    public const array PROBLEM_TOPICS = [
        ChatTopicEnum::MISSING_OR_DAMAGED->value,
        ChatTopicEnum::RETURN_REFUND->value,
        ChatTopicEnum::COMPLAINT->value,
        ChatTopicEnum::WEBSITE_PROBLEM->value,
    ];

    public string $commandSignature = 'hydrate:shop-customers-dashboard {shop? : shop slug}';

    public function getJobUniqueId(Shop $shop): string
    {
        return (string) $shop->id;
    }

    /**
     * A customer's invoices or email moved: its shop and every sister shop where the same email
     * buys see different numbers.
     */
    public static function dispatchForCustomer(Customer $customer, ?string $previousEmail = null): void
    {
        $shopIds = collect([$customer->email, $previousEmail])
            ->filter(fn ($email) => filled($email) && trim($email) !== '')
            ->flatMap(fn (string $email) => self::sameEmailCustomers($customer->group_id, $email)->distinct()->pluck('customers.shop_id'));

        Shop::whereIn('id', $shopIds->push($customer->shop_id)->unique())
            ->get()
            ->each(fn (Shop $shop) => self::dispatch($shop)->delay(now()->addMinutes(2)));
    }

    public function handle(Shop $shop): void
    {
        $today = now('UTC')->startOfDay();

        $shop->crmStats()->update([
            'customers_dashboard'            => json_encode([
                'base'          => $this->base($shop),
                'at_risk'       => $this->rows($this->customers($shop)
                    ->where('customers.state', CustomerStateEnum::LOSING->value)
                    ->where('customers.trade_state', '!=', CustomerTradeStateEnum::NONE->value)
                    ->orderByDesc('customer_stats.sales_all')),
                'overdue'       => $this->rows($this->customers($shop)
                    ->where('customers.state', CustomerStateEnum::ACTIVE->value)
                    ->whereBetween('customer_stats.expected_date_of_next_order', [$today->copy()->subDays(90), $today])
                    ->orderByDesc('customer_stats.sales_all')),
                'new_customers' => $this->rows($this->customers($shop)
                    ->where('customer_stats.first_order_date', '>=', $today->copy()->startOfMonth())
                    ->where('customers.trade_state', '!=', CustomerTradeStateEnum::NONE->value)
                    ->orderByDesc('customer_stats.first_order_date')),
                'top_customers' => collect(GetTopCustomersStats::run($shop, $today->copy()->subYear()->format('Ymd'), $today->format('Ymd'), self::LIMIT))
                    ->filter(fn ($customer) => $customer['sales'] > 0)
                    ->map(fn ($customer) => [
                        'slug'     => $customer['slug'],
                        'name'     => $customer['name'],
                        'sales'    => (float) $customer['sales'],
                        'invoices' => (int) $customer['invoices'],
                    ])->values()->all(),
                'sister_shops'  => $this->sisterShops($shop),
                'problems'      => $this->problems($shop, $today),
            ]),
            'customers_dashboard_hydrated_at' => now(),
        ]);
    }

    /**
     * The customer state alone misleads: most "active" customers registered and never ordered.
     * Buying regularly, slipping away and stopped buying count only customers who have ordered.
     */
    private function base(Shop $shop): array
    {
        $counts = DB::table('customers')
            ->where('shop_id', $shop->id)
            ->whereNull('deleted_at')
            ->where('state', '!=', CustomerStateEnum::IN_PROCESS->value)
            ->selectRaw('state, trade_state, count(*) as customers')
            ->groupBy('state', 'trade_state')
            ->get();

        $ordered = $counts->where('trade_state', '!=', CustomerTradeStateEnum::NONE->value);

        return [
            'ordered'       => (int) $ordered->sum('customers'),
            'active'        => (int) $ordered->where('state', CustomerStateEnum::ACTIVE->value)->sum('customers'),
            'losing'        => (int) $ordered->where('state', CustomerStateEnum::LOSING->value)->sum('customers'),
            'lost'          => (int) $ordered->where('state', CustomerStateEnum::LOST->value)->sum('customers'),
            'never_ordered' => (int) $counts->where('trade_state', CustomerTradeStateEnum::NONE->value)->sum('customers'),
            'one_order'     => (int) $ordered->where('trade_state', CustomerTradeStateEnum::ONE->value)->sum('customers'),
            'repeat'        => (int) $ordered->where('trade_state', CustomerTradeStateEnum::MANY->value)->sum('customers'),
        ];
    }

    /**
     * Buyers of this shop who also buy in another shop of the group with the same email, in group
     * currency so shops of different organisations add up.
     */
    private function sisterShops(Shop $shop): array
    {
        $pairs = $this->buyers(DB::table('customers as mine'), 'mine')
            ->where('mine.shop_id', $shop->id)
            ->join('customers as sister', function ($join) {
                $join->on('sister.group_id', '=', 'mine.group_id')
                    ->whereRaw('lower(trim(sister.email)) = lower(trim(mine.email))')
                    ->whereColumn('sister.shop_id', '!=', 'mine.shop_id')
                    ->whereNull('sister.deleted_at')
                    ->where('sister.trade_state', '!=', CustomerTradeStateEnum::NONE->value);
            })
            ->whereNotExists(fn ($query) => $query->from('org_partners')->whereColumn('org_partners.customer_id', 'sister.id'))
            ->join('customer_stats as mine_stats', 'mine_stats.customer_id', '=', 'mine.id')
            ->join('customer_stats as sister_stats', 'sister_stats.customer_id', '=', 'sister.id')
            ->select([
                'mine.id as customer_id',
                'mine.slug',
                'mine.name',
                'sister.shop_id',
                'mine_stats.sales_grp_currency_all as sales_here',
                'sister_stats.sales_grp_currency_all as sales_there',
            ])
            ->get();

        $shops = Shop::whereIn('id', $pairs->pluck('shop_id')->unique())->get(['id', 'code', 'name'])->keyBy('id');

        return [
            'currency_code' => $shop->group->currency->code,
            'buyers'        => $this->buyers(DB::table('customers as mine'), 'mine')->where('mine.shop_id', $shop->id)->count(),
            'shared_buyers' => $pairs->pluck('customer_id')->unique()->count(),
            'sales_here'    => round((float) $pairs->unique('customer_id')->sum('sales_here'), 2),
            'sales_there'   => round((float) $pairs->sum('sales_there'), 2),
            'shops'         => $pairs->groupBy('shop_id')
                ->map(fn ($rows, $shopId) => [
                    'code'      => $shops[$shopId]?->code,
                    'name'      => $shops[$shopId]?->name,
                    'customers' => $rows->pluck('customer_id')->unique()->count(),
                    'sales'     => round((float) $rows->sum('sales_there'), 2),
                ])
                ->sortByDesc('customers')->values()->all(),
            'top'           => $pairs->groupBy('customer_id')
                ->map(fn ($rows) => [
                    'slug'        => $rows->first()->slug,
                    'name'        => $rows->first()->name,
                    'sales_here'  => round((float) $rows->first()->sales_here, 2),
                    'sales_there' => round((float) $rows->sum('sales_there'), 2),
                    'shops'       => $rows->map(fn ($row) => $shops[$row->shop_id]?->code)->filter()->unique()->values()->all(),
                ])
                ->sortByDesc('sales_there')->take(self::LIMIT)->values()->all(),
        ];
    }

    /**
     * Chats and emails of the last 30 days whose topic is a problem, by topic and channel. Topics
     * come from the AI summary of each conversation, so unclassified ones are counted apart.
     */
    private function problems(Shop $shop, \Illuminate\Support\Carbon $today): array
    {
        $rows = DB::table('chat_sessions')
            ->where('shop_id', $shop->id)
            ->whereNull('deleted_at')
            ->where(fn ($query) => $query->whereNull('is_spam')->orWhere('is_spam', false))
            ->where(fn ($query) => $query->whereNull('is_rubbish')->orWhere('is_rubbish', false))
            ->where('created_at', '>=', $today->copy()->subDays(30))
            ->selectRaw('topic, channel, count(*) as conversations')
            ->groupBy('topic', 'channel')
            ->get();

        $problems = $rows->whereIn('topic', self::PROBLEM_TOPICS);

        return [
            'days'          => 30,
            'conversations' => (int) $rows->sum('conversations'),
            'classified'    => (int) $rows->whereNotNull('topic')->sum('conversations'),
            'problems'      => (int) $problems->sum('conversations'),
            'by_topic'      => collect(self::PROBLEM_TOPICS)
                ->map(fn (string $topic) => [
                    'topic'   => $topic,
                    'label'   => ChatTopicEnum::from($topic)->label(),
                    'chat'    => (int) $problems->where('topic', $topic)->where('channel', '!=', 'email')->sum('conversations'),
                    'email'   => (int) $problems->where('topic', $topic)->where('channel', 'email')->sum('conversations'),
                ])
                ->filter(fn (array $row) => $row['chat'] + $row['email'] > 0)
                ->sortByDesc(fn (array $row) => $row['chat'] + $row['email'])
                ->values()->all(),
        ];
    }

    private function buyers(Builder $query, string $alias): Builder
    {
        return $query
            ->whereNull("$alias.deleted_at")
            ->whereNotNull("$alias.email")
            ->whereRaw("trim($alias.email) != ''")
            ->where("$alias.trade_state", '!=', CustomerTradeStateEnum::NONE->value)
            ->whereNotExists(fn ($query) => $query->from('org_partners')->whereColumn('org_partners.customer_id', "$alias.id"));
    }

    private static function sameEmailCustomers(int $groupId, string $email): Builder
    {
        return DB::table('customers')
            ->where('customers.group_id', $groupId)
            ->whereNull('customers.deleted_at')
            ->whereRaw('lower(trim(customers.email)) = ?', [mb_strtolower(trim($email))]);
    }

    private function customers(Shop $shop): Builder
    {
        return DB::table('customers')
            ->join('customer_stats', 'customer_stats.customer_id', '=', 'customers.id')
            ->where('customers.shop_id', $shop->id)
            ->whereNull('customers.deleted_at')
            ->whereNotExists(fn ($query) => $query->from('org_partners')->whereColumn('org_partners.customer_id', 'customers.id'))
            ->select([
                'customers.slug',
                'customers.name',
                'customer_stats.number_invoices_type_invoice',
                'customer_stats.last_invoiced_at',
                'customer_stats.first_order_date',
                'customer_stats.expected_date_of_next_order',
                'customer_stats.sales_all',
            ])
            ->limit(self::LIMIT);
    }

    private function rows(Builder $query): array
    {
        return $query->get()->map(fn ($row) => [
            'slug'                => $row->slug,
            'name'                => $row->name,
            'invoices'            => (int) $row->number_invoices_type_invoice,
            'sales'               => (float) $row->sales_all,
            'last_invoiced_at'    => $row->last_invoiced_at,
            'first_order_date'    => $row->first_order_date,
            'expected_next_order' => $row->expected_date_of_next_order,
        ])->all();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $shops = Shop::query()
            ->when($command->argument('shop'), fn ($query, $slug) => $query->where('slug', $slug))
            ->where('state', '!=', ShopStateEnum::CLOSED)
            ->get();

        foreach ($shops as $shop) {
            self::dispatch($shop);
        }

        $command->info("Queued {$shops->count()} shops");

        return 0;
    }
}
