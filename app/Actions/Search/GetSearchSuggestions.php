<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class GetSearchSuggestions
{
    use AsAction;

    public function handle(): array
    {
        return cache()->remember('search-suggestions', 300, function () {
            try {
                $node = config('scout.typesense.client-settings.nodes.0');
                $hits = Http::withHeaders(['X-TYPESENSE-API-KEY' => config('scout.typesense.client-settings.api_key')])
                    ->timeout(2)
                    ->get($node['protocol'].'://'.$node['host'].':'.$node['port'].'/collections/'.SetupTypesenseSearchAnalytics::POPULAR_QUERIES_COLLECTION.'/documents/search', [
                        'q'        => '*',
                        'sort_by'  => 'count:desc',
                        'per_page' => 8,
                    ])->json('hits', []);

                return array_values(array_filter(array_map(
                    static fn (array $hit) => $hit['document']['q'] ?? null,
                    $hits
                )));
            } catch (Throwable) {
                return [];
            }
        });
    }

    public function asController(): array
    {
        return ['suggestions' => $this->handle()];
    }
}
