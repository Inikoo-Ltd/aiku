<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Aug 2024 22:33:24 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Search;

use App\Models\Catalogue\Product;
use Illuminate\Support\Facades\Artisan;
use Lorisleiva\Actions\Concerns\AsCommand;

class ReindexProductSearch
{
    use AsCommand;
    public string $commandSignature = 'reindex_search:products';


    public function asCommand(): void
    {
        Artisan::call('scout:import', ['model' => Product::class]);
    }


}
