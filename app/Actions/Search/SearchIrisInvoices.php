<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\Invoice;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Laravel\Scout\Builder as ScoutBuilder;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class SearchIrisInvoices
{
    use AsAction;
    use WithRawSearchResults;
    use WithTypesenseApi;

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
     * Same contract as the orders search: an invoice number is an identifier rather than
     * a phrase, so synonyms, curation overrides and any semantic or hybrid layer are
     * switched off explicitly and query_by stays a plain keyword match on the reference.
     *
     * @return array<string, mixed>
     */
    public static function searchParameters(string $query, int $customerId, ?int $shopId): array
    {
        $filters = ['customer_id:='.$customerId];
        if ($shopId) {
            $filters[] = 'shop_id:='.$shopId;
        }

        return array_merge(
            [
                'collection'             => (new Invoice())->searchableAs(),
                'q'                      => $query,
                'query_by'               => 'reference',
                'filter_by'              => implode(' && ', $filters),
                'per_page'               => self::HITS_LIMIT,
                'page'                   => 1,
                'prioritize_exact_match' => true,
                'prefix'                 => true,
                'enable_synonyms'        => false,
                'enable_overrides'       => false,
            ],
            SearchIrisOrders::SEARCH_TUNING
        );
    }

    /**
     * Fallback for non-typesense drivers (tests) and typesense outages.
     */
    public static function scoutQuery(string $query, int $customerId, ?int $shopId): ScoutBuilder
    {
        $invoicesQuery = Invoice::search($query)
            ->where('customer_id', $customerId)
            ->options(SearchIrisOrders::SEARCH_TUNING)
            ->take(self::HITS_LIMIT);

        if ($shopId) {
            $invoicesQuery->where('shop_id', $shopId);
        }

        return $invoicesQuery;
    }

    /**
     * Same safety contract as SearchIrisOrders::hydrate: every index hit is re-checked
     * against the customer in the database before anything is returned, so a stale
     * index can never leak another customer's invoice, and the direct reference match
     * covers fragments the index cannot reach.
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

        $invoicesQuery = $this->baseQuery($customerId)
            ->where(function (QueryBuilder $matchQuery) use ($ids, $like) {
                if ($ids) {
                    $matchQuery->whereIn('id', $ids);
                }
                if ($like) {
                    $matchQuery->orWhere('reference', 'ilike', $like);
                }
            });

        if ($shopId) {
            $invoicesQuery->where('shop_id', $shopId);
        }

        return $this->mapInvoices(
            $invoicesQuery
                ->with('shop')
                ->orderByRaw('array_position(ARRAY['.implode(',', $ids).']::bigint[], invoices.id) NULLS LAST')
                ->orderByDesc('date')
                ->limit(self::RESULTS_LIMIT)
                ->get()
        );
    }

    private function baseQuery(int $customerId): EloquentBuilder
    {
        return Invoice::query()
            ->where('customer_id', $customerId)
            ->where('in_process', false);
    }

    /**
     * @param Collection<int, Invoice> $invoices
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapInvoices(Collection $invoices): array
    {
        return $invoices
            ->map(fn (Invoice $invoice) => [
                'id'           => $invoice->id,
                'code'         => $invoice->reference,
                'type'         => $invoice->type->value,
                'date'         => $invoice->date,
                'total_amount' => $invoice->total_amount,
                'url'          => $this->invoiceUrl($invoice),
            ])
            ->all();
    }

    /**
     * Dropshipping storefronts serve invoices under their own prefix, ecom ones at the root.
     */
    private function invoiceUrl(Invoice $invoice): string
    {
        if ($invoice->shop?->type === ShopTypeEnum::DROPSHIPPING) {
            return '/app/dropshipping/invoices/'.$invoice->slug;
        }

        return '/app/invoices/'.$invoice->slug;
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
            logger()->warning("Typesense invoices search failed: $error");

            return [];
        }

        return Arr::pluck($response->json('results.0.hits', []), 'document');
    }
}
