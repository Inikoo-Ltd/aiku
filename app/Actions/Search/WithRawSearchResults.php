<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use Illuminate\Support\Arr;
use Laravel\Scout\Builder;

trait WithRawSearchResults
{
    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rawHits(Builder $query): array
    {
        return Arr::get($query->raw(), 'hits', []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rawDocuments(Builder $query): array
    {
        return Arr::pluck($this->rawHits($query), 'document');
    }

    /**
     * Typesense tags a hit found by the vector arm of a hybrid search with
     * vector_distance; a keyword hit carries no such key. Counting the arms apart
     * is what keeps assortment-gap reporting honest once hybrid starts answering
     * queries keyword search cannot: a gap is keyword = 0, however many related
     * products the customer was ultimately shown.
     *
     * @param array<int, array<string, mixed>> $hits
     *
     * @return array{keyword: int, vector: int}
     */
    protected function armCounts(array $hits): array
    {
        $vector = count(array_filter(
            $hits,
            static fn (array $hit) => isset($hit['vector_distance'])
        ));

        return [
            'keyword' => count($hits) - $vector,
            'vector'  => $vector,
        ];
    }

    /**
     * @param array<int, array{keyword: int, vector: int}> $counts
     *
     * @return array{keyword: int, vector: int}
     */
    protected function sumArmCounts(array $counts): array
    {
        return [
            'keyword' => array_sum(array_column($counts, 'keyword')),
            'vector'  => array_sum(array_column($counts, 'vector')),
        ];
    }
}
