<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;

class IndexSearchSynonymSuggestions extends OrgAction
{
    use WithTypesenseApi;
    use WithWebAuthorisation;

    /**
     * @return array<int, array{id: string, query: string, words: array<int, string>, sessions: int}>
     */
    public function handle(string $languageCode): array
    {
        $hits = $this->typesenseClient()
            ->get($this->typesenseUrl().'/collections/'.ProposeSearchSynonyms::SUGGESTIONS_COLLECTION.'/documents/search', [
                'q'         => '*',
                'query_by'  => 'query',
                'filter_by' => "language:=$languageCode && status:=pending",
                'sort_by'   => 'sessions:desc',
                'per_page'  => 100,
            ])
            ->json('hits', []);

        return collect($hits)
            ->map(fn (array $hit) => [
                'id'       => $hit['document']['id'],
                'query'    => $hit['document']['query'],
                'words'    => $hit['document']['words'],
                'sessions' => $hit['document']['sessions'],
            ])
            ->values()
            ->all();
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop->language->code);
    }
}
