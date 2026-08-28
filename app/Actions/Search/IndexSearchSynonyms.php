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

class IndexSearchSynonyms extends OrgAction
{
    use WithTypesenseApi;
    use WithWebAuthorisation;

    /**
     * @return array<int, array{id: string, synonyms: array<int, string>}>
     */
    public function handle(string $languageCode): array
    {
        $response = $this->typesenseClient()->get(
            $this->typesenseUrl().'/synonym_sets/'.StoreSearchSynonym::synonymSet($languageCode).'/items'
        );

        if (!$response->successful()) {
            return [];
        }

        return collect($response->json())
            ->map(fn (array $item) => [
                'id'       => $item['id'],
                'synonyms' => $item['synonyms'],
            ])
            ->sortBy('id')
            ->values()
            ->all();
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop->language->code);
    }
}
