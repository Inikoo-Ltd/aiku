<?php

/*
 * Author Louis Perez
 * Created on 24-09-2026-13h-23m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\Collection;

use App\Actions\Helpers\ClearCacheByWildcard;
use App\Models\Catalogue\Collection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/*
 * Makes the name and descriptions of a shop collection follow its master again.
 * The translations are copied as they are on the master. The visible text takes
 * the master translation in the shop language; without one it falls back to the
 * master text, but only when the shop follows master collections, which is what
 * UpdateMasterCollection would have pushed.
 */
class SyncCollectionContentFromMaster
{
    use AsAction;

    public function handle(Collection $collection): Collection
    {
        $masterCollection = $collection->masterCollection;
        if (!$masterCollection) {
            return $collection;
        }

        $locale       = $collection->shop->language->code;
        $followMaster = data_get($collection->shop->settings, 'catalog.collection_follow_master', false);

        foreach (['name', 'description', 'description_title', 'description_extra'] as $field) {
            $translations = $masterCollection->getTranslations($field.'_i8n');

            $collection->{$field.'_i8n'} = $translations;

            if (Arr::has($translations, $locale)) {
                $collection->{$field} = $translations[$locale];
            } elseif ($followMaster) {
                $collection->{$field} = $masterCollection->{$field};
            }
        }

        $collection->save();

        if ($collection->wasChanged('name') && $collection->webpage) {
            ClearCacheByWildcard::run("irisData:website:{$collection->webpage->website_id}:*");
        }

        return $collection;
    }
}
