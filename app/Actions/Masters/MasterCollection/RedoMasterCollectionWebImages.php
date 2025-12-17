<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 13 Dec 2025 10:26:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Masters\MasterCollection;

class RedoMasterCollectionWebImages
{
    use WithHydrateCommand;

    public string $commandSignature = 'master_collection:redo_web_images {--s|slug=} ';

    public function __construct()
    {
        $this->model = MasterCollection::class;
    }

    public function handle(MasterCollection $masterCollection): void
    {
        UpdateMasterCollectionWebImages::run($masterCollection);

    }

}
