<?php

/*
 * Author: Louis Perez Napitupulu
 * Created: Tue, 06 Oct 2026 16:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Catalogue\Product\Search;

use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Typesense\Client;

class ReindexProductVariantCodes
{
    use AsAction;

    public string $commandSignature = 'reindex_search:product_variant_codes';

    public string $commandDescription = 'Add the variant_codes field to the products search collection and reindex variant leaders';

    public function handle(?Command $command = null): int
    {
        $client     = new Client(config('scout.typesense.client-settings'));
        $collection = (new Product())->searchableAs();

        $fields = collect($client->collections[$collection]->retrieve()['fields'])->pluck('name');
        if ($fields->contains('variant_codes')) {
            $command?->info('variant_codes field already in '.$collection);
        } else {
            $client->collections[$collection]->update([
                'fields' => [['name' => 'variant_codes', 'type' => 'string', 'optional' => true]],
            ]);
            $command?->info('variant_codes field added to '.$collection);
        }

        $count = 0;
        Product::where('is_variant_leader', true)->chunkById(500, function ($leaders) use (&$count) {
            $leaders->first()->searchableUsing()->update($leaders);
            $count += $leaders->count();
        });
        $command?->info("Variant leaders reindexed: $count");

        return $count;
    }

    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }
}
