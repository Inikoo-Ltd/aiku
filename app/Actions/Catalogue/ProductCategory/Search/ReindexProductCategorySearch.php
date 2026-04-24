<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Aug 2024 22:13:51 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexProductCategorySearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:product_categories';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(ProductCategory::class, $reindex, $reset);
    }

}
