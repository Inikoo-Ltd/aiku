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
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class StoreSearchSynonym extends OrgAction
{
    use WithTypesenseApi;
    use WithWebAuthorisation;

    /**
     * One synonym set per language, shared by every shop in the group searching in
     * that language: a typo fix added by one shop team quietly helps all the others.
     * Applied at query time via the synonym_sets search parameter in SearchCatalogue.
     */
    public static function synonymSet(string $languageCode): string
    {
        return 'catalogue-'.$languageCode;
    }

    public static function synonymSetExistsCacheKey(string $languageCode): string
    {
        return 'typesense-synonym-set-exists:'.$languageCode;
    }

    public function handle(string $languageCode, array $words): array
    {
        $words = array_values(array_unique(array_map(
            static fn (string $word) => mb_strtolower(trim($word)),
            $words
        )));

        $id  = Str::slug($words[0]) ?: substr(md5(implode(',', $words)), 0, 12);
        $set = self::synonymSet($languageCode);

        $response = $this->typesenseClient()->put(
            $this->typesenseUrl()."/synonym_sets/$set/items/$id",
            ['synonyms' => $words]
        );

        if ($response->status() === 404) {
            $response = $this->typesenseClient()->put(
                $this->typesenseUrl()."/synonym_sets/$set",
                ['items' => [['id' => $id, 'synonyms' => $words]]]
            );
        }

        cache()->forget(self::synonymSetExistsCacheKey($languageCode));

        return ['id' => $id, 'synonyms' => $words, 'status' => $response->status()];
    }

    public function rules(): array
    {
        return [
            'words'   => ['required', 'array', 'min:2', 'max:10'],
            'words.*' => ['required', 'string', 'max:64'],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop->language->code, $this->validatedData['words']);
    }
}
