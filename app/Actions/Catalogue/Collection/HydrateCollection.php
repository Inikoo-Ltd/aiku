<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 11-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Collection;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Catalogue\Collection\Hydrators\CollectionHydrateItems;
use App\Models\Catalogue\Collection;

class HydrateCollection
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:collections {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = Collection::class;
    }

    public function handle(Collection $collection): void
    {
        CollectionHydrateItems::run($collection);
    }
}
