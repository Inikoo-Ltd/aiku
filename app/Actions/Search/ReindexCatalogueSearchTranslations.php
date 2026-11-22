<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Typesense\Client;

class ReindexCatalogueSearchTranslations
{
    use AsAction;

    public string $commandSignature = 'reindex_search:catalogue_translations';

    public string $commandDescription = 'Add the translated name and description fields to the products and product categories search collections and reindex the translated ones (INI-510)';

    private const array FIELDS = ['name_translations', 'description_translations'];

    public function handle(?Command $command = null): int
    {
        $client = new Client(config('scout.typesense.client-settings'));
        $count  = 0;

        foreach ([Product::class, ProductCategory::class] as $modelClass) {
            $collection = (new $modelClass())->searchableAs();

            $existing = collect($client->collections[$collection]->retrieve()['fields'])->pluck('name');
            $missing  = array_values(array_diff(self::FIELDS, $existing->all()));
            if ($missing) {
                $client->collections[$collection]->update([
                    'fields' => array_map(fn (string $field) => ['name' => $field, 'type' => 'string[]', 'optional' => true], $missing),
                ]);
                $command?->info('Fields added to '.$collection.': '.implode(', ', $missing));
            }
            cache()->forget(SearchCatalogue::translationsIndexedCacheKey($modelClass));

            $modelClass::query()
                ->where(fn ($query) => $query->whereRaw("coalesce(name_i8n, '{}'::jsonb) <> '{}'::jsonb")->orWhereRaw("coalesce(description_i8n, '{}'::jsonb) <> '{}'::jsonb"))
                ->chunkById(500, function ($models) use (&$count) {
                    $models->first()->searchableUsing()->update($models);
                    $count += $models->count();
                });
            $command?->info("$collection reindexed so far: $count");
        }

        return $count;
    }

    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }
}
