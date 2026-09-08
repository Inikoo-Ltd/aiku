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

class DeleteSearchSynonym extends OrgAction
{
    use WithTypesenseApi;
    use WithWebAuthorisation;

    public function handle(string $languageCode, string $synonymId): array
    {
        $response = $this->typesenseClient()->delete(
            $this->typesenseUrl().'/synonym_sets/'.StoreSearchSynonym::synonymSet($languageCode)."/items/$synonymId"
        );

        cache()->forget(StoreSearchSynonym::synonymSetExistsCacheKey($languageCode));

        return ['status' => $response->status()];
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, string $synonym, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop->language->code, $synonym);
    }
}
