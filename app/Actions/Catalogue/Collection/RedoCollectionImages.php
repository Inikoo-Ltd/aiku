<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Jun 2025 23:59:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Catalogue\Collection;

class RedoCollectionImages
{
    use WithHydrateCommand;

    public string $commandSignature = 'collections:redo_images {organisations?*} {--S|shop= shop slug} {--s|slug=} ';

    public function __construct()
    {
        $this->model = Collection::class;
    }

    public function handle(Collection $collection): void
    {
        UpdateCollectionImages::run($collection);

    }

}
