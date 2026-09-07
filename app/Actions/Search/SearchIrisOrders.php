<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Laravel\Scout\Builder as ScoutBuilder;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class SearchIrisOrders
{
    use AsAction;
    use WithRawSearchResults;
    use WithTypesenseApi;

    /**
     * Two typos from six characters onwards, so a customer who drops the country prefix
     * ("550706") or mistypes a digit ("GB550707") still reaches their order. Neighbouring
     * references coming along is accepted: the list is short and always their own.
     */
    public const array SEARCH_TUNING = [
        'typo_tokens_threshold' => 2,
        'min_len_2typo'         => 6,
        'num_typos'             => 2,
    ];

    protected const int HITS_LIMIT = 10;
    protected const int RESULTS_LIMIT = 5;
    protected const int MIN_DIRECT_MATCH_LENGTH = 3;

    public function handle(string $query, array $options): array
    {
        $customerId = Arr::get($options, 'customer_id');
        if (!$customerId) {
            return [];
        }

        $shopId = Arr::get($options, 'shop_id');

        if (config('scout.driver') === 'typesense') {
            try {
                $documents = $this->typesenseSearch($query, $customerId, $shopId);
            } catch (Throwable) {
                $documents = $this->rawDocuments(self::scoutQuery($query, $customerId, $shopId));
            }
        } else {
            $documents = $this->rawDocuments(self::scoutQuery($query, $customerId, $shopId));
        }

        return $this->hydrate($documents, $query, $customerId, $shopId);
    }

    /**
     * The one search in the storefront multi_search that is not part of the catalogue:
     * an order number is an identifier rather than a phrase, so synonyms, curation
     * overrides and any semantic or hybrid layer are switched off explicitly instead
     * of relying on defaults, and query_by stays a plain keyword match on the two
     * reference fields. Baskets and cancelled orders are filtered out at the index
     * so they do not eat into the hits limit.
     *
     * @return array<string, mixed>
     */
    public static function searchParameters(string $query, int $customerId, ?int $shopId): array
    {
        $filters = ['customer_id:='.$customerId];
        if ($shopId) {
            $filters[] = 'shop_id:='.$shopId;
        }
        $filters[] = 'state:!=['.OrderStateEnum::CREATING->value.','.OrderStateEnum::CANCELLED->value.']';

        return array_merge(
            [
                'collection'             => (new Order())->searchableAs(),
                'q'                      => $query,
                'query_by'               => 'reference,customer_reference',
                'filter_by'              => implode(' && ', $filters),
                'per_page'               => self::HITS_LIMIT,
                'page'                   => 1,
                'prioritize_exact_match' => true,
                'prefix'                 => true,
                'enable_synonyms'        => false,
                'enable_overrides'       => false,
            ],
            self::SEARCH_TUNING
        );
    }

    /**
     * Fallback for non-typesense drivers (tests) and typesense outages.
     */
    public static function scoutQuery(string $query, int $customerId, ?int $shopId): ScoutBuilder
    {
        $ordersQuery = Order::search($query)
            ->where('customer_id', $customerId)
            ->options(self::SEARCH_TUNING)
            ->take(self::HITS_LIMIT);

        if ($shopId) {
            $ordersQuery->where('shop_id', $shopId);
        }

        return $ordersQuery;
    }

    /**
     * Index hits and a direct match on the reference are resolved together in one query.
     *
     * The index alone is not enough twice over: its hits must be re-checked against the
     * customer, since a stale document would otherwise expose somebody else's order, and
     * its candidates for a short prefix are not a superset of the candidates for a longer
     * one, so "GB5" can miss an order that "GB55" returns. Matching the reference here
     * as well keeps a broader query from yielding fewer orders, and picks up the
     * fragments Typesense cannot reach at all ("0706" inside "GB550706").
     *
     * Index hits keep their relevance order and lead, direct matches follow by date.
     *
     * @param array<int, array<string, mixed>> $documents
     *
     * @return array<int, array<string, mixed>>
     */
    public function hydrate(array $documents, string $query, int $customerId, ?int $shopId = null): array
    {
        $ids  = array_values(array_unique(array_map('intval', array_filter(array_column($documents, 'id')))));
        $term = trim($query);
        $like = mb_strlen($term) >= self::MIN_DIRECT_MATCH_LENGTH
            ? '%'.addcslashes($term, '%_\\').'%'
            : null;

        if (empty($ids) && !$like) {
            return [];
        }

        $ordersQuery = $this->baseQuery($customerId)
            ->where(function (QueryBuilder $matchQuery) use ($ids, $like) {
                if ($ids) {
                    $matchQuery->whereIn('id', $ids);
                }
                if ($like) {
                    $matchQuery->orWhere('reference', 'ilike', $like)
                        ->orWhere('customer_reference', 'ilike', $like);
                }
            });

        if ($shopId) {
            $ordersQuery->where('shop_id', $shopId);
        }

        return $this->mapOrders(
            $ordersQuery
                ->with(['shop', 'customerSalesChannel'])
                ->orderByRaw('array_position(ARRAY['.implode(',', $ids).']::bigint[], orders.id) NULLS LAST')
                ->orderByDesc('date')
                ->limit(self::RESULTS_LIMIT)
                ->get()
        );
    }

    private function baseQuery(int $customerId): EloquentBuilder
    {
        return Order::query()
            ->where('customer_id', $customerId)
            ->whereNotIn('state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED]);
    }

    /**
     * @param Collection<int, Order> $orders
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapOrders(Collection $orders): array
    {
        return $orders
            ->map(fn (Order $order) => [
                'id'                 => $order->id,
                'code'               => $order->reference,
                'customer_reference' => $order->customer_reference,
                'state'              => $order->state->value,
                'state_label'        => Arr::get(OrderStateEnum::labels(), $order->state->value),
                'state_icon'         => Arr::get(OrderStateEnum::stateIcon(), $order->state->value),
                'date'               => $order->date,
                'total_amount'       => $order->total_amount,
                'url'                => $this->orderUrl($order),
            ])
            ->all();
    }

    /**
     * Dropshipping storefronts show orders inside their sales channel, ecom ones at the root.
     */
    private function orderUrl(Order $order): string
    {
        if ($order->shop?->type === ShopTypeEnum::DROPSHIPPING && $order->customerSalesChannel) {
            return '/app/dropshipping/channels/'.$order->customerSalesChannel->slug.'/orders/'.$order->slug;
        }

        return '/app/orders/'.$order->slug;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function typesenseSearch(string $query, int $customerId, ?int $shopId): array
    {
        $response = $this->typesenseClient()
            ->post($this->typesenseUrl().'/multi_search', ['searches' => [self::searchParameters($query, $customerId, $shopId)]])
            ->throw();

        if ($error = $response->json('results.0.error')) {
            logger()->warning("Typesense orders search failed: $error");

            return [];
        }

        return Arr::pluck($response->json('results.0.hits', []), 'document');
    }
}
