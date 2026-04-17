<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Aug 2024 22:13:51 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\Search;

use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Facades\Artisan;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexProductCategorySearch
{
    use AsAction;

    public string $commandSignature = 'reindex_search:product_categories';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        if ($reset) {
            Artisan::call('scout:flush', [
                'model' => ProductCategory::class
            ]);
        }

        if ($reindex) {
            Artisan::call('scout:queue-import', [
                'model'   => ProductCategory::class,
                '--chunk' => 1000
            ]);
        }
    }

}
