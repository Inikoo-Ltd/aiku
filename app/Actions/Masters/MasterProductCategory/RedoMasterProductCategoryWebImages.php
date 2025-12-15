<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Jun 2025 23:59:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Masters\MasterProductCategory;

class RedoMasterProductCategoryWebImages
{
    use WithHydrateCommand;

    public string $commandSignature = 'master_product_categories:redo_web_images {--s|slug=} ';

    public function __construct()
    {
        $this->model = MasterProductCategory::class;
    }

    public function handle(MasterProductCategory $masterProductCategory): void
    {
        UpdateMasterProductCategoryWebImages::run($masterProductCategory);

    }

}
