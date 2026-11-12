<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Search\RecordWebsiteSearchClick;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantAnswerKindEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantChallengeTypeEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantHandOverReasonEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantHandOverRouteEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Helpers\Currency;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The numbers of the AI Assistant performance page (HELP-4608).
 *
 * A line is "ordered after being shown" when the conversation's customer submitted it (variant options
 * counted for their leader) at or after the moment the product was first shown in the conversation and
 * before that moment plus services.shopping_assistant.attribution_days. A line two conversations showed
 * counts once, for the earliest showing among all the customer's conversations.
 *
 * The strict figures are a lower bound: only an add from a card or a new line of a bulk add records
 * an `added` event with its transaction. A quantity raised later in the basket is an `increased`
 * event, which no figure of this report counts.
 *
 * new_families looks for an earlier ordered line of each (customer, family) pair of the attributed
 * lines. The pairs are few, as they come from attributed lines only, and each lookup goes through
 * the customer_id and family_id indexes of transactions.
 *
 * ponytail: each (conversation, shown product) reads the customer's lines through transactions.customer_id,
 * so a customer with tens of thousands of lines shown many products repeats that read. Add an index on
 * transactions (customer_id, submitted_at) if the page gets slow.
 *
 * ponytail: computed on every page open, no cache; the page is opened rarely. Cache per filter set if it becomes busy.
 *
 * @phpstan-type Filters array{website_ids: array<int, int>, search_website_ids: array<int, int>, language: ?string, entry_point: ?string, shop_type: ?string, country: ?string}
 */
class GetShoppingAssistantReport
{
    use AsObject;

    public const array QUESTION_BUCKETS = ['1', '2', '3', '4+'];

    /**
     * @param  Filters  $filters
     */
    public function handle(array $filters, CarbonImmutable $from, CarbonImmutable $to, string $currencyCode, bool $inShopCurrency): array
    {
        $from           = $from->startOfDay();
        $to             = $to->endOfDay();
        $days           = (int) $from->diffInDays($to->startOfDay()) + 1;
        $previousFrom   = $from->subDays($days);
        $previousTo     = $from->subSecond();
        $attributionDays = $this->attributionDays();
        $amountColumn   = $inShopCurrency ? 'net_amount' : 'grp_net_amount';

        $matureDay   = CarbonImmutable::now()->startOfDay()->subDays($attributionDays);
        $matureUntil = $this->matureUntil($to, $matureDay);
        $granularity = $days <= 62 ? 'day' : 'week';

        $current  = $this->headline($filters, $from, $to, $amountColumn, $granularity, $matureUntil?->endOfDay());
        $previous = $this->headline($filters, $previousFrom, $previousTo, $amountColumn, $granularity, $this->matureUntil($previousTo, $matureDay)?->endOfDay());

        $usdRate      = $this->usdRate($currencyCode);
        $costCurrency = $usdRate === null ? 'USD' : $currencyCode;
        $costOf       = fn (array $period) => $period['cost'] * ($usdRate ?? 1);

        $metric = fn (string $key) => ['value' => $current[$key], 'previous' => $previous[$key]];

        return [
            'mature_until'  => $matureUntil?->toDateString(),
            'kpis'          => [
                'conversations'            => $metric('conversations'),
                'customers'                => $metric('customers'),
                'orders'                   => $metric('orders'),
                'revenue'                  => $metric('revenue'),
                'strict_orders'            => $metric('strict_orders'),
                'strict_revenue'           => $metric('strict_revenue'),
                'conversion_rate'          => $metric('conversion_rate'),
                'avg_minutes'              => $metric('avg_minutes'),
                'avg_messages'             => $metric('avg_messages'),
                'returning_customers'      => $metric('returning_customers'),
                'ai_cost'                  => ['value' => $costOf($current), 'previous' => $costOf($previous)],
                'ai_cost_per_conversation' => [
                    'value'    => $current['conversations'] ? $costOf($current) / $current['conversations'] : 0,
                    'previous' => $previous['conversations'] ? $costOf($previous) / $previous['conversations'] : 0,
                ],
                'ai_cost_share'            => $usdRate === null || $current['revenue'] <= 0 ? null : [
                    'value'    => $costOf($current) / $current['revenue'],
                    'previous' => $previous['revenue'] > 0 ? $costOf($previous) / $previous['revenue'] : null,
                ],
                'aov_assistant'            => $metric('aov_assistant'),
                'aov_others'               => $metric('aov_others'),
                'new_families'             => $metric('new_families'),
            ],
            'cost_currency' => $costCurrency,
            'funnel'        => array_map(
                fn (string $key) => ['key' => $key, 'value' => $current['funnel'][$key], 'previous' => $previous['funnel'][$key]],
                ['conversations', 'shown', 'clicked', 'added', 'ordered']
            ),
            'trend'         => [
                'granularity' => $granularity,
                'points'      => $this->trendPoints($current['buckets'], $from, $to, $granularity, $matureUntil),
            ],
            'drop_off'      => array_map(
                fn (string $bucket) => ['questions' => $bucket, 'conversations' => $current['drop_off'][$bucket]],
                self::QUESTION_BUCKETS
            ),
            'families'      => $this->families($filters, $from, $to, $amountColumn),
            'search'          => $this->search($filters, $from, $to, $previousFrom, $previousTo),
            'assistant_reach' => $this->assistantReach($filters, $from, $to, $previousFrom, $previousTo),
            'refusals'        => $this->refusals($filters, $from, $to, $previousFrom, $previousTo),
            'general'         => $this->general($filters, $from, $to, $previousFrom, $previousTo, $usdRate ?? 1.0),
            'feedback'        => [
                'current'  => $this->feedback($filters, $from, $to),
                'previous' => $this->feedback($filters, $previousFrom, $previousTo),
            ],
            'challenges'      => $this->challenges($filters, $from, $to),
        ];
    }

    /**
     * Thumbs up and down the customers gave to answers of the conversations in scope.
     *
     * @param  Filters  $filters
     *
     * @return array{answers: int, up: int, down: int}
     */
    private function feedback(array $filters, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $conversations = $this->conversationsQuery($filters, $from, $to);

        $row = DB::selectOne(
            <<<SQL
            with convs as ({$conversations->toSql()})
            select count(*) as answers,
                count(*) filter (where messages.feedback = 'up') as up,
                count(*) filter (where messages.feedback = 'down') as down
            from shopping_assistant_messages messages
            join convs on convs.id = messages.shopping_assistant_conversation_id
            where messages.role = 'assistant'
            SQL,
            $conversations->getBindings()
        );

        return ['answers' => (int) $row->answers, 'up' => (int) $row->up, 'down' => (int) $row->down];
    }

    /**
     * What stood between customers and an order, as tagged by TagShoppingAssistantConversationChallenges.
     * by_type counts conversations; top groups the same (type, topic) across conversations and keeps
     * the latest quote and conversation as the example.
     *
     * @param  Filters  $filters
     *
     * @return array{tagged: int, untagged: int, by_type: array<int, array{type: string, label: string, conversations: int}>, top: array<int, array{type: string, label: string, topic: string, conversations: int, quote: ?string, conversation: string}>}
     */
    public function challenges(array $filters, CarbonImmutable $from, CarbonImmutable $to, int $limit = 20): array
    {
        $conversations = $this->conversationsQuery($filters, $from, $to);
        $labels        = ShoppingAssistantChallengeTypeEnum::labels();

        $coverage = DB::selectOne(
            <<<SQL
            with convs as ({$conversations->toSql()})
            select count(*) filter (where conversations.challenges_checked_at is not null) as tagged,
                count(*) filter (where conversations.challenges_checked_at is null and conversations.wiped_at is null) as untagged
            from convs
            join shopping_assistant_conversations conversations on conversations.id = convs.id
            SQL,
            $conversations->getBindings()
        );

        $byType = DB::select(
            <<<SQL
            with convs as ({$conversations->toSql()})
            select challenges.type, count(distinct challenges.shopping_assistant_conversation_id) as conversations
            from shopping_assistant_challenges challenges
            join convs on convs.id = challenges.shopping_assistant_conversation_id
            group by challenges.type
            order by 2 desc
            SQL,
            $conversations->getBindings()
        );

        $top = DB::select(
            <<<SQL
            with convs as ({$conversations->toSql()})
            select challenges.type, challenges.topic,
                count(distinct challenges.shopping_assistant_conversation_id) as conversations,
                (array_agg(challenges.quote order by challenges.created_at desc) filter (where challenges.quote is not null))[1] as quote,
                (array_agg(conversations.ulid order by challenges.created_at desc))[1] as conversation
            from shopping_assistant_challenges challenges
            join convs on convs.id = challenges.shopping_assistant_conversation_id
            join shopping_assistant_conversations conversations on conversations.id = challenges.shopping_assistant_conversation_id
            where challenges.topic <> ''
            group by challenges.type, challenges.topic
            order by 3 desc, max(challenges.created_at) desc
            limit ?
            SQL,
            [...$conversations->getBindings(), $limit]
        );

        return [
            'tagged'   => (int) $coverage->tagged,
            'untagged' => (int) $coverage->untagged,
            'by_type'  => array_map(fn ($row) => ['type' => $row->type, 'label' => $labels[$row->type] ?? $row->type, 'conversations' => (int) $row->conversations], $byType),
            'top'      => array_map(fn ($row) => [
                'type'            => $row->type,
                'label'           => $labels[$row->type] ?? $row->type,
                'topic'           => $row->topic,
                'conversations'   => (int) $row->conversations,
                'quote'           => $row->quote,
                'conversation'    => $row->conversation,
            ], $top),
        ];
    }

    public function attributionDays(): int
    {
        return (int) config('services.shopping_assistant.attribution_days', 7);
    }

    /**
     * The conversations the report counts. The caller has joined `customers` on the conversation's customer.
     *
     * @param  Filters  $filters
     */
    public function applyScope(Builder|EloquentBuilder $query, array $filters, CarbonImmutable $from, CarbonImmutable $to): Builder|EloquentBuilder
    {
        return $query
            ->whereIn('shopping_assistant_conversations.website_id', $filters['website_ids'])
            ->where('shopping_assistant_conversations.created_at', '>=', $from->toIso8601String())
            ->where('shopping_assistant_conversations.created_at', '<=', $to->toIso8601String())
            ->where('shopping_assistant_conversations.entry_point', '!=', ShoppingAssistantEntryPointEnum::COMMAND->value)
            ->whereRaw('coalesce(customers.is_staff, false) = false')
            ->when($filters['language'], fn ($query, $language) => $query->where('shopping_assistant_conversations.language_code', $language))
            ->when($filters['entry_point'], fn ($query, $entryPoint) => $query->where('shopping_assistant_conversations.entry_point', $entryPoint))
            ->when($filters['shop_type'], fn ($query, $shopType) => $query->where('customers.shop_type', $shopType))
            ->when($filters['country'], fn ($query, $country) => $query->whereRaw("customers.location->>0 = ?", [$country]));
    }

    /**
     * @param  Filters  $filters
     */
    public function conversationsQuery(array $filters, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $query = DB::table('shopping_assistant_conversations')
            ->leftJoin('customers', 'customers.id', '=', 'shopping_assistant_conversations.customer_id')
            ->select([
                'shopping_assistant_conversations.id',
                'shopping_assistant_conversations.customer_id',
                'shopping_assistant_conversations.created_at',
                'shopping_assistant_conversations.last_message_at',
                'shopping_assistant_conversations.messages_count',
                'shopping_assistant_conversations.cost',
            ]);

        return $this->applyScope($query, $filters, $from, $to);
    }

    /**
     * Runs $select after the CTEs `convs` (the conversations of $conversations), `matches` (every order
     * line attributed to a conversation of convs) and `lines` (each attributed line once, for the
     * earliest conversation of the customer that showed it, kept when that conversation is in convs).
     *
     * @param  array<int, mixed>  $selectBindings
     *
     * @return array<int, object>
     */
    public function selectWithAttribution(Builder $conversations, string $amountColumn, string $select, array $selectBindings = []): array
    {
        return DB::select(...$this->attributionSql($conversations, $amountColumn, $select, $selectBindings));
    }

    /**
     * The statement of selectWithAttribution and its bindings, for use as a subquery.
     *
     * @param  array<int, mixed>  $selectBindings
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    public function attributionSql(Builder $conversations, string $amountColumn, string $select, array $selectBindings = []): array
    {
        $amountColumn = $amountColumn === 'net_amount' ? 'net_amount' : 'grp_net_amount';

        $sql = <<<SQL
            with convs as ({$conversations->toSql()}),
            customer_conversations as (
                select customer_conversations.id, customer_conversations.customer_id
                from shopping_assistant_conversations customer_conversations
                where customer_conversations.customer_id in (select convs.customer_id from convs)
                    and customer_conversations.entry_point <> ?
            ),
            first_shown as (
                select events.shopping_assistant_conversation_id as conversation_id, customer_conversations.customer_id, events.product_id, min(events.created_at) as shown_at
                from shopping_assistant_events events
                join customer_conversations on customer_conversations.id = events.shopping_assistant_conversation_id
                where events.type = 'shown'
                group by events.shopping_assistant_conversation_id, customer_conversations.customer_id, events.product_id
            ),
            all_matches as (
                select first_shown.conversation_id, first_shown.product_id, first_shown.shown_at, transactions.id as transaction_id,
                    transactions.order_id, transactions.customer_id, transactions.family_id, transactions.submitted_at, transactions.{$amountColumn} as amount
                from first_shown
                join transactions on transactions.customer_id = first_shown.customer_id
                join products bought on bought.id = transactions.model_id
                left join variants on variants.id = bought.variant_id
                where transactions.model_type = 'Product'
                    and transactions.submitted_at >= first_shown.shown_at
                    and transactions.submitted_at < first_shown.shown_at + make_interval(days => ?::int)
                    and transactions.state not in (?, ?)
                    and transactions.deleted_at is null
                    and transactions.is_gift = false
                    and coalesce(variants.leader_id, bought.id) = first_shown.product_id
            ),
            matches as (
                select * from all_matches where all_matches.conversation_id in (select convs.id from convs)
            ),
            lines as (
                select * from (
                    select distinct on (all_matches.transaction_id) * from all_matches order by all_matches.transaction_id, all_matches.shown_at, all_matches.conversation_id
                ) earliest
                where earliest.conversation_id in (select convs.id from convs)
            )
            {$select}
            SQL;

        return [$sql, [
            ...$conversations->getBindings(),
            ShoppingAssistantEntryPointEnum::COMMAND->value,
            $this->attributionDays(),
            ...$this->notOrderedStates(),
            ...$selectBindings,
        ]];
    }

    /**
     * @return array<int, string>
     */
    public function notOrderedStates(): array
    {
        return [TransactionStateEnum::CREATING->value, TransactionStateEnum::CANCELLED->value];
    }

    private function matureUntil(CarbonImmutable $to, CarbonImmutable $matureDay): ?CarbonImmutable
    {
        return $to->gt($matureDay->endOfDay()) ? $matureDay : null;
    }

    /**
     * @param  Filters  $filters
     */
    private function headline(array $filters, CarbonImmutable $from, CarbonImmutable $to, string $amountColumn, string $granularity, ?CarbonImmutable $matureEnd): array
    {
        $conversations = $this->conversationsQuery($filters, $from, $to);
        $matureEnd     ??= $to;

        $buckets = collect($this->selectWithAttribution(
            $conversations,
            $amountColumn,
            <<<'SQL'
            , flags as (
                select convs.id, convs.created_at, convs.last_message_at, convs.messages_count, convs.cost,
                    exists (select 1 from shopping_assistant_events events where events.shopping_assistant_conversation_id = convs.id and events.type = 'shown') as was_shown,
                    exists (select 1 from shopping_assistant_events events where events.shopping_assistant_conversation_id = convs.id and events.type = 'clicked') as was_clicked,
                    exists (select 1 from shopping_assistant_events events where events.shopping_assistant_conversation_id = convs.id and events.type = 'added') as was_added,
                    exists (select 1 from matches where matches.conversation_id = convs.id) as was_ordered,
                    (select count(*) from shopping_assistant_messages messages where messages.shopping_assistant_conversation_id = convs.id and messages.role = 'customer') as questions
                from convs
            )
            select (date_trunc(?, flags.created_at at time zone ?))::date as bucket,
                count(*) as conversations,
                count(*) filter (where was_shown) as shown,
                count(*) filter (where was_clicked) as clicked,
                count(*) filter (where was_added) as added,
                count(*) filter (where was_ordered) as ordered,
                count(*) filter (where created_at <= ?) as mature,
                count(*) filter (where was_ordered and created_at <= ?) as mature_ordered,
                count(*) filter (where questions <= 1) as questions_1,
                count(*) filter (where questions = 2) as questions_2,
                count(*) filter (where questions = 3) as questions_3,
                count(*) filter (where questions >= 4) as questions_4,
                coalesce(sum(extract(epoch from last_message_at - created_at) / 60) filter (where last_message_at > created_at), 0) as minutes,
                count(*) filter (where last_message_at > created_at) as timed,
                coalesce(sum(messages_count), 0) as messages,
                coalesce(sum(cost), 0) as cost
            from flags
            group by 1
            order by 1
            SQL,
            [$granularity, config('app.timezone'), $matureEnd->toIso8601String(), $matureEnd->toIso8601String()]
        ));

        $sum = fn (string $column) => (float) $buckets->sum(fn ($bucket) => (float) $bucket->{$column});

        $conversationsCount = (int) $sum('conversations');
        $mature             = $sum('mature');

        $money = $this->selectWithAttribution(
            $conversations,
            $amountColumn,
            <<<SQL
            , strict_lines as (
                select distinct transactions.id, transactions.order_id, transactions.{$amountColumn} as amount
                from shopping_assistant_events events
                join convs on convs.id = events.shopping_assistant_conversation_id
                join transactions on transactions.id = events.transaction_id
                where events.type = 'added'
                    and transactions.submitted_at is not null
                    and transactions.state not in (?, ?)
                    and transactions.deleted_at is null
            ),
            family_pairs as (
                select lines.customer_id, lines.family_id, min(lines.submitted_at) as first_at
                from lines
                where lines.family_id is not null
                group by lines.customer_id, lines.family_id
            )
            select
                (select count(distinct lines.order_id) from lines) as orders,
                (select coalesce(sum(lines.amount), 0) from lines) as revenue,
                (select count(distinct strict_lines.order_id) from strict_lines) as strict_orders,
                (select coalesce(sum(strict_lines.amount), 0) from strict_lines) as strict_revenue,
                (select count(*) from family_pairs where not exists (
                    select 1 from transactions earlier
                    where earlier.customer_id = family_pairs.customer_id
                        and earlier.family_id = family_pairs.family_id
                        and earlier.model_type = 'Product'
                        and earlier.submitted_at < family_pairs.first_at
                        and earlier.state not in (?, ?)
                        and earlier.deleted_at is null
                        and earlier.is_gift = false
                )) as new_families
            SQL,
            [...$this->notOrderedStates(), ...$this->notOrderedStates()]
        )[0];

        $customers = DB::selectOne(
            <<<SQL
            with convs as ({$conversations->toSql()})
            select count(*) as customers, count(*) filter (where per_customer.conversations > 1 or per_customer.earlier) as returning_customers
            from (
                select convs.customer_id, count(*) as conversations,
                    exists (
                        select 1 from shopping_assistant_conversations earlier
                        where earlier.customer_id = convs.customer_id and earlier.created_at < ? and earlier.entry_point <> ?
                    ) as earlier
                from convs
                where convs.customer_id is not null
                group by convs.customer_id
            ) per_customer
            SQL,
            [...$conversations->getBindings(), $from->toIso8601String(), ShoppingAssistantEntryPointEnum::COMMAND->value]
        );

        $aov = $this->averageOrderValues($conversations, $filters, $from, $to, $amountColumn);

        return [
            'conversations'       => $conversationsCount,
            'customers'           => (int) $customers->customers,
            'returning_customers' => (int) $customers->returning_customers,
            'orders'              => (int) $money->orders,
            'revenue'             => (float) $money->revenue,
            'strict_orders'       => (int) $money->strict_orders,
            'strict_revenue'      => (float) $money->strict_revenue,
            'new_families'        => (int) $money->new_families,
            'conversion_rate'     => $mature > 0 ? $sum('mature_ordered') / $mature : 0.0,
            'avg_minutes'         => $sum('timed') > 0 ? $sum('minutes') / $sum('timed') : 0.0,
            'avg_messages'        => $conversationsCount ? $sum('messages') / $conversationsCount : 0.0,
            'cost'                => $sum('cost'),
            'aov_assistant'       => $aov['assistant'],
            'aov_others'          => $aov['others'],
            'funnel'              => [
                'conversations' => $conversationsCount,
                'shown'         => (int) $sum('shown'),
                'clicked'       => (int) $sum('clicked'),
                'added'         => (int) $sum('added'),
                'ordered'       => (int) $sum('ordered'),
            ],
            'drop_off'            => [
                '1'  => (int) $sum('questions_1'),
                '2'  => (int) $sum('questions_2'),
                '3'  => (int) $sum('questions_3'),
                '4+' => (int) $sum('questions_4'),
            ],
            'buckets'             => $buckets->keyBy(fn ($bucket) => (string) $bucket->bucket)->all(),
        ];
    }

    /**
     * Orders submitted in the period on the shops of the websites, staff customers left out, split
     * between the customers who had a conversation of the report and the rest.
     *
     * @param  Filters  $filters
     *
     * @return array{assistant: float, others: float}
     */
    private function averageOrderValues(Builder $conversations, array $filters, CarbonImmutable $from, CarbonImmutable $to, string $amountColumn): array
    {
        $shops = DB::table('websites')->whereIn('id', $filters['website_ids'])->get(['shop_id', 'organisation_id']);
        if ($shops->isEmpty()) {
            return ['assistant' => 0.0, 'others' => 0.0];
        }

        $organisationPlaceholders = implode(', ', array_fill(0, $shops->pluck('organisation_id')->unique()->count(), '?'));
        $shopPlaceholders         = implode(', ', array_fill(0, $shops->count(), '?'));

        $row = DB::selectOne(
            <<<SQL
            with convs as ({$conversations->toSql()})
            select coalesce(avg(amount) filter (where used_assistant), 0) as assistant, coalesce(avg(amount) filter (where not used_assistant), 0) as others
            from (
                select orders.{$amountColumn} as amount, exists (select 1 from convs where convs.customer_id = orders.customer_id) as used_assistant
                from orders
                join customers on customers.id = orders.customer_id
                where orders.organisation_id in ({$organisationPlaceholders})
                    and orders.shop_id in ({$shopPlaceholders})
                    and orders.submitted_at >= ?
                    and orders.submitted_at <= ?
                    and orders.deleted_at is null
                    and orders.state not in (?, ?)
                    and customers.is_staff = false
            ) submitted_orders
            SQL,
            [
                ...$conversations->getBindings(),
                ...$shops->pluck('organisation_id')->unique()->values()->all(),
                ...$shops->pluck('shop_id')->all(),
                $from->toIso8601String(),
                $to->toIso8601String(),
                ...$this->notOrderedStates(),
            ]
        );

        return ['assistant' => (float) $row->assistant, 'others' => (float) $row->others];
    }

    /**
     * @param  array<string, object>  $buckets
     */
    private function trendPoints(array $buckets, CarbonImmutable $from, CarbonImmutable $to, string $granularity, ?CarbonImmutable $matureUntil): array
    {
        $points = [];
        $date   = $granularity === 'week' ? $from->startOfWeek(CarbonInterface::MONDAY) : $from->startOfDay();

        while ($date->lte($to)) {
            $bucket    = $buckets[$date->toDateString()] ?? null;
            $bucketEnd = $granularity === 'week' ? $date->addDays(6)->min($to->startOfDay()) : $date;

            $points[] = [
                'date'          => $date->toDateString(),
                'conversations' => (int) ($bucket->conversations ?? 0),
                'shown'         => (int) ($bucket->shown ?? 0),
                'clicked'       => (int) ($bucket->clicked ?? 0),
                'added'         => (int) ($bucket->added ?? 0),
                'ordered'       => (int) ($bucket->ordered ?? 0),
                'complete'      => $matureUntil === null || $bucketEnd->lte($matureUntil),
            ];

            $date = $granularity === 'week' ? $date->addWeek() : $date->addDay();
        }

        return $points;
    }

    /**
     * @param  Filters  $filters
     */
    private function families(array $filters, CarbonImmutable $from, CarbonImmutable $to, string $amountColumn): array
    {
        $rows = $this->selectWithAttribution(
            $this->conversationsQuery($filters, $from, $to),
            $amountColumn,
            <<<'SQL'
            , family_events as (
                select events.shopping_assistant_conversation_id as conversation_id, events.type, products.family_id
                from shopping_assistant_events events
                join convs on convs.id = events.shopping_assistant_conversation_id
                join products on products.id = events.product_id
                where products.family_id is not null
            ),
            family_orders as (
                select products.family_id, count(distinct matches.conversation_id) as ordered
                from matches join products on products.id = matches.product_id
                group by products.family_id
            ),
            family_revenue as (
                select products.family_id, sum(lines.amount) as revenue
                from lines join products on products.id = lines.product_id
                group by products.family_id
            )
            select families.id, families.code, families.name,
                count(distinct family_events.conversation_id) filter (where family_events.type = 'shown') as shown,
                count(distinct family_events.conversation_id) filter (where family_events.type = 'clicked') as clicked,
                count(distinct family_events.conversation_id) filter (where family_events.type = 'added') as added,
                coalesce(max(family_orders.ordered), 0) as ordered,
                coalesce(max(family_revenue.revenue), 0) as revenue
            from family_events
            join product_categories families on families.id = family_events.family_id
            left join family_orders on family_orders.family_id = families.id
            left join family_revenue on family_revenue.family_id = families.id
            group by families.id, families.code, families.name
            order by shown desc, families.code
            limit 15
            SQL
        );

        return array_map(fn ($row) => [
            'id'      => (int) $row->id,
            'code'    => $row->code,
            'name'    => $row->name,
            'shown'   => (int) $row->shown,
            'clicked' => (int) $row->clicked,
            'added'   => (int) $row->added,
            'ordered' => (int) $row->ordered,
            'revenue' => (float) $row->revenue,
        ], $rows);
    }

    /**
     * Submitted searches of the websites. A search log carries no entry point or customer segment, so
     * only the dates, the website and the language (the language of the website's shop) apply.
     *
     * @param  Filters  $filters
     */
    private function search(array $filters, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $previousFrom, CarbonImmutable $previousTo): array
    {
        $logs = fn (CarbonImmutable $periodFrom, CarbonImmutable $periodTo) => DB::table('website_search_logs')
            ->whereIn('website_id', $filters['search_website_ids'])
            ->where('scope', 'catalogue_page')
            ->where('created_at', '>=', $periodFrom->toIso8601String())
            ->where('created_at', '<=', $periodTo->toIso8601String());

        $offeredKinds = [
            'did_you_mean'   => "suggestions->>'did_you_mean' is not null",
            'related_search' => "suggestions->'searches'->>'kind' = 'related' and jsonb_array_length(suggestions->'searches'->'items') > 0",
            'popular_search' => "suggestions->'searches'->>'kind' = 'popular' and jsonb_array_length(suggestions->'searches'->'items') > 0",
            'family'         => "jsonb_array_length(suggestions->'families') > 0",
            'best_sellers'   => "jsonb_array_length(suggestions->'products'->'best_sellers') > 0",
            'new_arrivals'   => "jsonb_array_length(suggestions->'products'->'new_arrivals') > 0",
            'forgotten'      => "jsonb_array_length(suggestions->'products'->'forgotten') > 0",
        ];

        $totals = fn (CarbonImmutable $periodFrom, CarbonImmutable $periodTo) => $logs($periodFrom, $periodTo)
            ->selectRaw('count(*) as searches')
            ->selectRaw('count(*) filter (where results_count = 0) as zero_results')
            ->selectRaw('count(suggestion_clicked_at) as suggestion_clicks')
            ->selectRaw('count(suggestion_clicked_at) filter (where results_count = 0) as zero_rescued')
            ->selectRaw('count(suggestions_viewed_at) as seen')
            ->selectRaw('count(suggestions) as offered')
            ->first();

        $current  = $totals($from, $to);
        $previous = $totals($previousFrom, $previousTo);

        $metric   = fn (string $key) => ['value' => (int) $current->{$key}, 'previous' => (int) $previous->{$key}];
        $zeroRate = fn ($period) => $period->searches ? $period->zero_results / $period->searches : 0;

        $shownByKind = (array) $logs($from, $to)
            ->whereNotNull('suggestions')
            ->selectRaw(collect($offeredKinds)->map(fn (string $condition, string $kind) => "count(*) filter (where $condition) as $kind")->implode(', '))
            ->first();

        $clicksByKind = DB::table('website_search_log_clicks')
            ->whereIn('website_id', $filters['search_website_ids'])
            ->where('type', 'suggestion')
            ->where('created_at', '>=', $from->toIso8601String())
            ->where('created_at', '<=', $to->toIso8601String())
            ->selectRaw('kind, count(*) as clicks')
            ->groupBy('kind')
            ->pluck('clicks', 'kind');

        $grouped = fn () => $logs($from, $to)
            ->selectRaw('lower(trim(query)) as query, count(*) as searches')
            ->whereRaw('length(trim(query)) >= 3')
            ->groupByRaw('lower(trim(query))')
            ->orderByDesc('searches')
            ->orderBy('query')
            ->limit(10);

        return [
            'ignored_filters'   => array_values(array_filter(['entry_point', 'shop_type', 'country'], fn (string $key) => $filters[$key] !== null)),
            'searches'          => $metric('searches'),
            'zero_results'      => $metric('zero_results'),
            'zero_result_rate'  => ['value' => $zeroRate($current), 'previous' => $zeroRate($previous)],
            'suggestion_clicks' => $metric('suggestion_clicks'),
            'zero_rescued'      => $metric('zero_rescued'),
            'seen'              => $metric('seen'),
            'offered'           => $metric('offered'),
            'by_kind'           => array_map(
                fn (string $kind) => ['kind' => $kind, 'shown' => (int) ($shownByKind[$kind] ?? 0), 'clicks' => (int) ($clicksByKind[$kind] ?? 0)],
                RecordWebsiteSearchClick::SUGGESTION_KINDS
            ),
            'top_queries'       => $grouped()->selectRaw('count(clicked_at) as clicks')->get()
                ->map(fn ($row) => ['query' => $row->query, 'searches' => (int) $row->searches, 'clicks' => (int) $row->clicks])->all(),
            'top_zero_queries'  => $grouped()->where('results_count', 0)->selectRaw('count(suggestion_clicked_at) as rescued')->get()
                ->map(fn ($row) => ['query' => $row->query, 'searches' => (int) $row->searches, 'rescued' => (int) $row->rescued])->all(),
        ];
    }

    /**
     * How many searches could have become a question. Search logs of both the dropdown and the results
     * page count, as the Ask button is offered in both: `available` when it was offered, `offered`
     * when such a search also read like a question (4 or more words or a "?", the storefront's rule)
     * or found nothing, `asked` the conversations opened from the search box. Only the dates, the
     * website and the language apply: a search log has no entry point or customer segment, and
     * `asked` leaves the entry point, shop type and country filters aside too, to stay comparable.
     *
     * @param  Filters  $filters
     */
    private function assistantReach(array $filters, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $previousFrom, CarbonImmutable $previousTo): array
    {
        $period = function (CarbonImmutable $periodFrom, CarbonImmutable $periodTo) use ($filters) {
            $searches = DB::table('website_search_logs')
                ->whereIn('website_id', $filters['search_website_ids'])
                ->where('created_at', '>=', $periodFrom->toIso8601String())
                ->where('created_at', '<=', $periodTo->toIso8601String())
                ->where('assistant_available', true)
                ->selectRaw('count(*) as available')
                ->selectRaw("count(*) filter (where results_count = 0 or position(? in query) > 0 or array_length(regexp_split_to_array(trim(query), '\s+'), 1) >= 4) as offered", ['?'])
                ->first();

            $asked = $this->conversationsQuery(
                [...$filters, 'entry_point' => ShoppingAssistantEntryPointEnum::SEARCH->value, 'shop_type' => null, 'country' => null],
                $periodFrom,
                $periodTo
            )->count();

            return ['available' => (int) $searches->available, 'offered' => (int) $searches->offered, 'asked' => $asked];
        };

        $current  = $period($from, $to);
        $previous = $period($previousFrom, $previousTo);

        return collect($current)->map(fn (int $value, string $key) => ['value' => $value, 'previous' => $previous[$key]])->all();
    }

    /**
     * Questions refused by the rate limit or a spent monthly budget, staff customers left out. Only
     * the dates, the website and the language (of the website's shop) apply; the entry point, shop
     * type and country filters are not applied.
     *
     * @param  Filters  $filters
     */
    private function refusals(array $filters, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $previousFrom, CarbonImmutable $previousTo): array
    {
        $counts = fn (CarbonImmutable $periodFrom, CarbonImmutable $periodTo) => DB::table('shopping_assistant_refusals')
            ->join('customers', 'customers.id', '=', 'shopping_assistant_refusals.customer_id')
            ->whereIn('shopping_assistant_refusals.website_id', $filters['search_website_ids'])
            ->where('shopping_assistant_refusals.created_at', '>=', $periodFrom->toIso8601String())
            ->where('shopping_assistant_refusals.created_at', '<=', $periodTo->toIso8601String())
            ->whereRaw('coalesce(customers.is_staff, false) = false')
            ->selectRaw('shopping_assistant_refusals.reason, count(*) as refusals')
            ->groupBy('shopping_assistant_refusals.reason')
            ->pluck('refusals', 'reason');

        $current  = $counts($from, $to);
        $previous = $counts($previousFrom, $previousTo);

        return collect([StoreShoppingAssistantRefusal::RATE_LIMIT, StoreShoppingAssistantRefusal::BUDGET])
            ->mapWithKeys(fn (string $reason) => [$reason => ['value' => (int) ($current[$reason] ?? 0), 'previous' => (int) ($previous[$reason] ?? 0)]])
            ->all();
    }

    /**
     * Drill-down values of the Conversations tab's `general` filter that stand alone; the others carry
     * a value after a colon (kind:, source:, reason:, offer:, route:).
     */
    public const array GENERAL_FILTERS = ['questions', 'answered', 'offered', 'handed_over', 'wrong', 'gap', 'in_hours', 'after_hours', 'replied', 'closed', 'ordered', 'thumbs_up', 'thumbs_down', 'challenges'];

    /**
     * @return array<string, string>
     */
    public static function sourceLabels(): array
    {
        return [
            'policy'    => __('Policy pages'),
            'shop_fact' => __('Shop settings'),
            'guide'     => __('Guides'),
            'note'      => __('Staff notes'),
            'verified'  => __('Verified facts'),
            'learned'   => __('Learned rules'),
        ];
    }

    /**
     * The `general` filter value when the page offers it, else null (an unknown value is dropped).
     */
    public function validGeneralFilter(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        if (in_array($value, self::GENERAL_FILTERS, true)) {
            return $value;
        }

        [$prefix, $suffix] = array_pad(explode(':', $value, 2), 2, '');

        $allowed = match ($prefix) {
            'kind'   => ShoppingAssistantAnswerKindEnum::general(),
            'reason', 'offer' => ShoppingAssistantHandOverReasonEnum::values(),
            'route'  => ShoppingAssistantHandOverRouteEnum::values(),
            'challenge' => ShoppingAssistantChallengeTypeEnum::values(),
            default  => null,
        };

        if ($prefix === 'source') {
            return preg_match('/^[a-z_]{1,32}$/', $suffix) ? $value : null;
        }

        return $allowed !== null && in_array($suffix, $allowed, true) ? $value : null;
    }

    /**
     * "A general question": an assistant message whose answer_kind is one of
     * ShoppingAssistantAnswerKindEnum::general(). Messages from before the feature have no answer_kind
     * and are not counted.
     */
    public function generalQuestionCondition(string $alias): string
    {
        return "{$alias}.role = 'assistant' and {$alias}.answer_kind in ({$this->quoted(ShoppingAssistantAnswerKindEnum::general())})";
    }

    /**
     * A general question the assistant answered itself: shop knowledge, a family description or its own data.
     */
    public function answeredQuestionCondition(string $alias): string
    {
        $kinds = [
            ShoppingAssistantAnswerKindEnum::SHOP_ANSWER->value,
            ShoppingAssistantAnswerKindEnum::FAMILY_DESCRIPTION->value,
            ShoppingAssistantAnswerKindEnum::TOOL->value,
        ];

        return "{$alias}.role = 'assistant' and {$alias}.answer_kind in ({$this->quoted($kinds)})";
    }

    /**
     * When a person first answered the chat of a hand-over: the earliest undeleted message of the chat
     * from an agent after the hand-over.
     */
    public function firstHumanReplySql(string $handOver): string
    {
        return "(select min(chat_replies.created_at) from chat_messages chat_replies where chat_replies.chat_session_id = {$handOver}.chat_session_id and chat_replies.sender_type = 'agent' and chat_replies.deleted_at is null and chat_replies.created_at > {$handOver}.created_at)";
    }

    public function chatClosedCondition(string $handOver): string
    {
        return "exists (select 1 from chat_sessions closed_chats where closed_chats.id = {$handOver}.chat_session_id and closed_chats.status in ('closed', 'resolved'))";
    }

    /**
     * A hand-over old enough to judge whether the customer ordered afterwards: older than the attribution window.
     */
    public function handOverMatureCondition(string $handOver): string
    {
        return "{$handOver}.created_at <= now() - make_interval(days => {$this->attributionDays()})";
    }

    /**
     * The customer of the hand-over's conversation submitted an order (not cancelled, not deleted, the
     * same conditions as the report's order values) within the attribution window after the hand-over.
     */
    public function orderedAfterHandOverCondition(string $handOver): string
    {
        return "exists (select 1 from orders after_orders join shopping_assistant_conversations ordered_conversations on ordered_conversations.id = {$handOver}.shopping_assistant_conversation_id where after_orders.customer_id = ordered_conversations.customer_id and after_orders.submitted_at >= {$handOver}.created_at and after_orders.submitted_at < {$handOver}.created_at + make_interval(days => {$this->attributionDays()}) and after_orders.deleted_at is null and after_orders.state not in ({$this->quoted($this->notOrderedStates())}))";
    }

    /**
     * The correlated condition on `shopping_assistant_conversations` that keeps the conversations behind
     * one figure of the general questions section; null for a value the page does not offer. The figures
     * and this drill-down are built from the same fragments above.
     */
    public function generalFilterCondition(?string $filter): ?string
    {
        $filter = $this->validGeneralFilter($filter);
        if ($filter === null) {
            return null;
        }

        $conversation = 'shopping_assistant_conversations.id';
        $message      = fn (string $condition) => "exists (select 1 from shopping_assistant_messages filter_messages where filter_messages.shopping_assistant_conversation_id = {$conversation} and {$condition})";
        $handOver     = fn (string $condition = 'true') => "exists (select 1 from shopping_assistant_hand_overs filter_hand_overs where filter_hand_overs.shopping_assistant_conversation_id = {$conversation} and {$condition})";

        [$prefix, $suffix] = array_pad(explode(':', $filter, 2), 2, '');

        return match (true) {
            $filter === 'questions'   => $message($this->generalQuestionCondition('filter_messages')),
            $filter === 'answered'    => $message($this->answeredQuestionCondition('filter_messages')),
            $filter === 'offered'     => $message("filter_messages.role = 'assistant' and filter_messages.hand_over_offer is not null"),
            $filter === 'handed_over' => $handOver(),
            $filter === 'wrong'       => $message("filter_messages.role = 'assistant' and filter_messages.staff_rating = 'wrong'"),
            $filter === 'gap'         => $message("filter_messages.role = 'assistant' and filter_messages.hand_over_offer = 'not_sure'"),
            $filter === 'in_hours'    => $handOver('filter_hand_overs.in_hours = true'),
            $filter === 'after_hours' => $handOver('filter_hand_overs.in_hours = false'),
            $filter === 'replied'     => $handOver($this->firstHumanReplySql('filter_hand_overs').' is not null'),
            $filter === 'closed'      => $handOver($this->chatClosedCondition('filter_hand_overs')),
            $filter === 'ordered'     => $handOver($this->handOverMatureCondition('filter_hand_overs').' and '.$this->orderedAfterHandOverCondition('filter_hand_overs')),
            $prefix === 'kind'        => $message("filter_messages.role = 'assistant' and filter_messages.answer_kind = '{$suffix}'"),
            $prefix === 'source'      => $message("filter_messages.role = 'assistant' and filter_messages.answer_kind = 'shop_answer' and filter_messages.source_kind = '{$suffix}'"),
            $prefix === 'offer'       => $message("filter_messages.role = 'assistant' and filter_messages.hand_over_offer = '{$suffix}'"),
            $prefix === 'reason'      => $handOver("filter_hand_overs.reason = '{$suffix}'"),
            $prefix === 'route'       => $handOver("filter_hand_overs.route = '{$suffix}'"),
            $filter === 'thumbs_up'   => $message("filter_messages.role = 'assistant' and filter_messages.feedback = 'up'"),
            $filter === 'thumbs_down' => $message("filter_messages.role = 'assistant' and filter_messages.feedback = 'down'"),
            $filter === 'challenges'  => "exists (select 1 from shopping_assistant_challenges filter_challenges where filter_challenges.shopping_assistant_conversation_id = {$conversation})",
            $prefix === 'challenge'   => "exists (select 1 from shopping_assistant_challenges filter_challenges where filter_challenges.shopping_assistant_conversation_id = {$conversation} and filter_challenges.type = '{$suffix}')",
            default                   => null,
        };
    }

    /**
     * @param  array<int, string>  $values  trusted literals (enum values, constants)
     */
    private function quoted(array $values): string
    {
        return implode(', ', array_map(fn (string $value) => "'{$value}'", $values));
    }

    /**
     * How the assistant handles general questions about the shop and hand-overs to a person (INI-245), for
     * exactly the conversations of the report's scope in the period, and where a figure is a
     * {value, previous} pair, in the previous period too.
     *
     * - questions: general questions, the assistant messages whose answer_kind is shop_answer,
     *   family_description, tool or offer_person. Messages from before the feature have no answer_kind and are not counted.
     * - answered: general questions the assistant answered itself (shop_answer, family_description, tool).
     * - by_source: what the self-answered questions drew on: the source_kind of shop answers, family descriptions, our own data.
     * - offers: assistant messages that offered a person (hand_over_offer set); by_reason splits them by
     *   reason next to the hand-overs that really happened for that reason.
     * - hand_overs: rows of shopping_assistant_hand_overs, one per customer who tapped "ask the team"; by_route splits them.
     * - rate_per_question: hand_overs per general question; rate_per_conversation: conversations with a hand-over per conversation.
     * - first_reply: the wait between a hand-over and the first message from a person in its chat, in
     *   chat hours and outside them, with the median of the replied ones. Raw clock time: outside chat
     *   hours the wait includes the night, which is why the two are shown apart.
     * - outcomes: of the hand-overs, how many were replied to by a person, how many chats are closed
     *   or resolved, and how many customers placed an order within the attribution days after the
     *   hand-over; only hand-overs older than that window can be judged (ordered_base).
     * - wrong_answers: assistant answers the chat team marked wrong.
     * - ai_cost: the cost of the general-question answers, in the page currency like the other cost figures.
     * - gaps: the questions behind "not sure" offers, the knowledge the assistant lacks. Grouped by the
     *   exact text (lower case, spaces collapsed, cut to 300 characters); grouping by meaning is a later ticket.
     *
     * @param  Filters  $filters
     */
    private function general(array $filters, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $previousFrom, CarbonImmutable $previousTo, float $usdRate): array
    {
        $current  = $this->generalFigures($filters, $from, $to);
        $previous = $this->generalFigures($filters, $previousFrom, $previousTo);
        $pair     = fn (callable $figure) => ['value' => $figure($current), 'previous' => $figure($previous)];
        $rate     = fn (int $numerator, int $denominator) => $denominator > 0 ? $numerator / $denominator : null;

        $handOverRows = collect($current['hand_over_rows']);
        $reasonLabels = ShoppingAssistantHandOverReasonEnum::labels();
        $routeLabels  = ShoppingAssistantHandOverRouteEnum::labels();
        $offered      = $current['offered_by_reason'];

        $firstReply = function (bool $inHours) use ($handOverRows, $current) {
            $rows = $handOverRows->filter(fn (array $row) => $row['in_hours'] === $inHours);

            return [
                'hand_overs'     => $rows->sum('hand_overs'),
                'replied'        => $rows->sum('replied'),
                'median_minutes' => $current['median_minutes'][(int) $inHours] ?? null,
            ];
        };

        return [
            'questions'             => $pair(fn (array $period) => $period['questions']),
            'answered'              => $pair(fn (array $period) => $period['answered']),
            'by_source'             => $this->generalBySource($filters, $from, $to),
            'offers'                => $pair(fn (array $period) => $period['offers']),
            'by_reason'             => array_map(fn (string $reason) => [
                'reason'     => $reason,
                'label'      => $reasonLabels[$reason],
                'offered'    => (int) ($offered[$reason] ?? 0),
                'handed_over' => (int) $handOverRows->where('reason', $reason)->sum('hand_overs'),
            ], ShoppingAssistantHandOverReasonEnum::values()),
            'hand_overs'            => $pair(fn (array $period) => $period['hand_overs']),
            'by_route'              => array_map(fn (string $route) => [
                'route'      => $route,
                'label'      => $routeLabels[$route],
                'hand_overs' => (int) $handOverRows->where('route', $route)->sum('hand_overs'),
            ], ShoppingAssistantHandOverRouteEnum::values()),
            'rate_per_question'     => [
                'value'    => $rate($current['hand_overs'], $current['questions']),
                'previous' => $rate($previous['hand_overs'], $previous['questions']),
            ],
            'rate_per_conversation' => [
                'value'    => $rate($current['conversations_with_hand_over'], $current['conversations']),
                'previous' => $rate($previous['conversations_with_hand_over'], $previous['conversations']),
            ],
            'first_reply'           => ['in_hours' => $firstReply(true), 'after_hours' => $firstReply(false)],
            'outcomes'              => [
                'hand_overs'   => $current['hand_overs'],
                'replied'      => (int) $handOverRows->sum('replied'),
                'closed'       => (int) $handOverRows->sum('closed'),
                'ordered'      => (int) $handOverRows->sum('ordered'),
                'ordered_base' => (int) $handOverRows->sum('mature'),
            ],
            'wrong_answers'         => $pair(fn (array $period) => $period['wrong']),
            'ai_cost'               => $pair(fn (array $period) => $period['cost'] * $usdRate),
            'gaps'                  => $this->generalGaps($filters, $from, $to),
        ];
    }

    /**
     * @param  Filters  $filters
     *
     * @return array{questions: int, answered: int, offers: int, wrong: int, cost: float, conversations: int, conversations_with_hand_over: int, hand_overs: int, offered_by_reason: array<string, int>, hand_over_rows: array<int, array{reason: string, route: string, in_hours: bool, hand_overs: int, replied: int, closed: int, mature: int, ordered: int}>, median_minutes: array<int, float>}
     */
    private function generalFigures(array $filters, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $conversations = $this->conversationsQuery($filters, $from, $to);
        $bindings      = $conversations->getBindings();

        $general  = $this->generalQuestionCondition('msgs');
        $answered = $this->answeredQuestionCondition('msgs');

        $totals = DB::selectOne(
            <<<SQL
            with convs as ({$conversations->toSql()}),
            msgs as (
                select shopping_assistant_messages.* from shopping_assistant_messages
                join convs on convs.id = shopping_assistant_messages.shopping_assistant_conversation_id
                where shopping_assistant_messages.role = 'assistant'
            )
            select
                (select count(*) from convs) as conversations,
                (select count(distinct hand_overs.shopping_assistant_conversation_id) from shopping_assistant_hand_overs hand_overs join convs on convs.id = hand_overs.shopping_assistant_conversation_id) as conversations_with_hand_over,
                count(*) filter (where {$general}) as questions,
                count(*) filter (where {$answered}) as answered,
                count(*) filter (where msgs.hand_over_offer is not null) as offers,
                count(*) filter (where msgs.staff_rating = 'wrong') as wrong,
                coalesce(sum(msgs.cost) filter (where {$general}), 0) as cost
            from msgs
            SQL,
            $bindings
        );

        $offered = DB::select(
            <<<SQL
            with convs as ({$conversations->toSql()})
            select shopping_assistant_messages.hand_over_offer as reason, count(*) as offered
            from shopping_assistant_messages
            join convs on convs.id = shopping_assistant_messages.shopping_assistant_conversation_id
            where shopping_assistant_messages.role = 'assistant' and shopping_assistant_messages.hand_over_offer is not null
            group by 1
            SQL,
            $bindings
        );

        $reply   = $this->firstHumanReplySql('hos');
        $closed  = $this->chatClosedCondition('hos');
        $mature  = $this->handOverMatureCondition('hos');
        $ordered = $this->orderedAfterHandOverCondition('hos');

        $handOvers = DB::select(
            <<<SQL
            with convs as ({$conversations->toSql()}),
            hos as (
                select shopping_assistant_hand_overs.* from shopping_assistant_hand_overs
                join convs on convs.id = shopping_assistant_hand_overs.shopping_assistant_conversation_id
            )
            select hos.reason, hos.route, hos.in_hours, count(*) as hand_overs,
                count(*) filter (where {$reply} is not null) as replied,
                count(*) filter (where {$closed}) as closed,
                count(*) filter (where {$mature}) as mature,
                count(*) filter (where {$mature} and {$ordered}) as ordered
            from hos
            group by 1, 2, 3
            SQL,
            $bindings
        );

        $medians = DB::select(
            <<<SQL
            with convs as ({$conversations->toSql()}),
            hos as (
                select shopping_assistant_hand_overs.* from shopping_assistant_hand_overs
                join convs on convs.id = shopping_assistant_hand_overs.shopping_assistant_conversation_id
            )
            select hos.in_hours, percentile_cont(0.5) within group (order by extract(epoch from ({$reply} - hos.created_at)) / 60) as median_minutes
            from hos
            where {$reply} is not null
            group by 1
            SQL,
            $bindings
        );

        $handOverRows = array_map(fn ($row) => [
            'reason'     => (string) $row->reason,
            'route'      => (string) $row->route,
            'in_hours'   => (bool) $row->in_hours,
            'hand_overs' => (int) $row->hand_overs,
            'replied'    => (int) $row->replied,
            'closed'     => (int) $row->closed,
            'mature'     => (int) $row->mature,
            'ordered'    => (int) $row->ordered,
        ], $handOvers);

        return [
            'questions'                    => (int) $totals->questions,
            'answered'                     => (int) $totals->answered,
            'offers'                       => (int) $totals->offers,
            'wrong'                        => (int) $totals->wrong,
            'cost'                         => (float) $totals->cost,
            'conversations'                => (int) $totals->conversations,
            'conversations_with_hand_over' => (int) $totals->conversations_with_hand_over,
            'hand_overs'                   => array_sum(array_column($handOverRows, 'hand_overs')),
            'offered_by_reason'            => collect($offered)->mapWithKeys(fn ($row) => [(string) $row->reason => (int) $row->offered])->all(),
            'hand_over_rows'               => $handOverRows,
            'median_minutes'               => collect($medians)->mapWithKeys(fn ($row) => [(int) (bool) $row->in_hours => (float) $row->median_minutes])->all(),
        ];
    }

    /**
     * @param  Filters  $filters
     *
     * @return array<int, array{key: string, label: string, answers: int, general: string}>
     */
    private function generalBySource(array $filters, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $conversations = $this->conversationsQuery($filters, $from, $to);
        $answered      = $this->answeredQuestionCondition('shopping_assistant_messages');

        $rows = DB::select(
            <<<SQL
            with convs as ({$conversations->toSql()})
            select shopping_assistant_messages.answer_kind, shopping_assistant_messages.source_kind, count(*) as answers
            from shopping_assistant_messages
            join convs on convs.id = shopping_assistant_messages.shopping_assistant_conversation_id
            where {$answered}
            group by 1, 2
            SQL,
            $conversations->getBindings()
        );

        $sourceLabels = self::sourceLabels();
        $kindLabels   = ShoppingAssistantAnswerKindEnum::labels();

        return collect($rows)
            ->map(function ($row) use ($sourceLabels, $kindLabels) {
                if ($row->answer_kind === ShoppingAssistantAnswerKindEnum::SHOP_ANSWER->value && $row->source_kind !== null) {
                    return ['key' => "source:{$row->source_kind}", 'label' => $sourceLabels[$row->source_kind] ?? $row->source_kind, 'answers' => (int) $row->answers, 'general' => "source:{$row->source_kind}"];
                }

                if ($row->answer_kind === ShoppingAssistantAnswerKindEnum::SHOP_ANSWER->value) {
                    return ['key' => 'source:none', 'label' => __('Other'), 'answers' => (int) $row->answers, 'general' => 'kind:shop_answer'];
                }

                $label = $row->answer_kind === ShoppingAssistantAnswerKindEnum::FAMILY_DESCRIPTION->value ? __('Family descriptions') : $kindLabels[$row->answer_kind];

                return ['key' => "kind:{$row->answer_kind}", 'label' => $label, 'answers' => (int) $row->answers, 'general' => "kind:{$row->answer_kind}"];
            })
            ->groupBy('key')
            ->map(fn ($group) => [...$group->first(), 'answers' => $group->sum('answers')])
            ->filter(fn (array $row) => $row['answers'] > 0)
            ->sortByDesc('answers')
            ->values()
            ->all();
    }

    /**
     * @param  Filters  $filters
     *
     * @return array<int, array{question: string, count: int, languages: array<int, string>, last_asked_at: string, conversation: string}>
     */
    private function generalGaps(array $filters, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $conversations = $this->conversationsQuery($filters, $from, $to);

        $rows = DB::select(
            <<<SQL
            with convs as ({$conversations->toSql()}),
            offers as (
                select shopping_assistant_messages.id, shopping_assistant_messages.created_at, gap_conversations.language_code, gap_conversations.ulid,
                    (select questions.body from shopping_assistant_messages questions
                        where questions.shopping_assistant_conversation_id = shopping_assistant_messages.shopping_assistant_conversation_id
                            and questions.role = 'customer' and questions.id < shopping_assistant_messages.id
                        order by questions.id desc limit 1) as question
                from shopping_assistant_messages
                join convs on convs.id = shopping_assistant_messages.shopping_assistant_conversation_id
                join shopping_assistant_conversations gap_conversations on gap_conversations.id = convs.id
                where shopping_assistant_messages.role = 'assistant' and shopping_assistant_messages.hand_over_offer = 'not_sure'
            )
            select left(lower(trim(regexp_replace(offers.question, '\s+', ' ', 'g'))), 300) as question,
                count(*) as asked,
                string_agg(distinct offers.language_code, ',') as languages,
                max(offers.created_at) as last_asked_at,
                (array_agg(offers.ulid order by offers.created_at desc, offers.id desc))[1] as conversation
            from offers
            where trim(coalesce(offers.question, '')) <> ''
            group by 1
            order by asked desc, last_asked_at desc
            limit 20
            SQL,
            $conversations->getBindings()
        );

        return array_map(fn ($row) => [
            'question'      => (string) $row->question,
            'count'         => (int) $row->asked,
            'languages'     => $row->languages ? explode(',', $row->languages) : [],
            'last_asked_at' => CarbonImmutable::parse($row->last_asked_at)->toIso8601String(),
            'conversation'  => (string) $row->conversation,
        ], $rows);
    }

    /**
     * The conversation cost is in USD; null when there is no rate to the page currency.
     */
    public function usdRate(string $currencyCode): ?float
    {
        if ($currencyCode === 'USD') {
            return 1.0;
        }

        $usd    = Currency::where('code', 'USD')->first();
        $target = Currency::where('code', $currencyCode)->first();
        if (!$usd || !$target) {
            return null;
        }

        return GetCurrencyExchange::run($usd, $target) ?: null;
    }
}
