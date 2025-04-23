<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Production\RawMaterial\Search;

use App\Actions\HydrateModel;
use App\Models\Production\RawMaterial;
use Illuminate\Support\Collection;

class ReindexRawMaterialRecordSearch extends HydrateModel
{
    public string $commandSignature = 'search:raw_material {organisations?*} {--s|slugs=}';


    public function handle(RawMaterial $rawMaterial): void
    {
        RawMaterialRecordSearch::run($rawMaterial);
    }

    protected function getModel(string $slug): RawMaterial
    {
        return RawMaterial::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return RawMaterial::withTrashed()->get();
    }
}
