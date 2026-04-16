<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Aug 2024 22:33:24 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Search;

use App\Models\Catalogue\Product;
use Illuminate\Support\Facades\Artisan;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexProductSearch
{
    use AsAction;

    public string $commandSignature = 'reindex_search:products';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        if ($reset) {
            Artisan::call('scout:flush', [
                'model' => Product::class
            ]);
        }
        if ($reindex) {
            Artisan::call('scout:queue-import', [
                'model'   => Product::class,
                '--chunk' => 1000
            ]);
        }
    }


}
