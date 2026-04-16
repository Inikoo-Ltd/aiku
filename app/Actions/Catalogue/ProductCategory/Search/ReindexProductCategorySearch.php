<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Aug 2024 22:13:51 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\Search;

use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Facades\Artisan;
use Lorisleiva\Actions\Concerns\AsCommand;

class ReindexProductCategorySearch
{
    use AsCommand;

    public string $commandSignature = 'reindex_search:product_categories';

    public function asCommand(): void
    {
        Artisan::call('scout:queue-import', [
            'model'   => ProductCategory::class,
            '--chunk' => 1000
        ]);
    }

}
