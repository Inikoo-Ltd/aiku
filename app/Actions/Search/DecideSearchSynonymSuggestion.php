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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DecideSearchSynonymSuggestion extends OrgAction
{
    use WithTypesenseApi;
    use WithWebAuthorisation;

    public function handle(string $languageCode, string $suggestionId, string $decision): array
    {
        $documentUrl = $this->typesenseUrl().'/collections/'.ProposeSearchSynonyms::SUGGESTIONS_COLLECTION."/documents/$suggestionId";

        $suggestion = $this->typesenseClient()->get($documentUrl)->json();
        if (!is_array($suggestion) || ($suggestion['language'] ?? null) !== $languageCode) {
            throw new NotFoundHttpException('Suggestion not found');
        }

        if ($decision === 'approve') {
            StoreSearchSynonym::make()->handle($languageCode, $suggestion['words']);
        }

        $this->typesenseClient()->patch($documentUrl, [
            'status' => $decision === 'approve' ? 'approved' : 'dismissed',
        ]);

        return ['id' => $suggestionId, 'status' => $decision === 'approve' ? 'approved' : 'dismissed'];
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, string $suggestion, string $decision, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop->language->code, $suggestion, $decision);
    }
}
