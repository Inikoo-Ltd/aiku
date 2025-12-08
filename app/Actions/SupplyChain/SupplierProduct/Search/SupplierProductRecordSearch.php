<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 14:38:32 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct\Search;

use App\Models\SupplyChain\SupplierProduct;
use Lorisleiva\Actions\Concerns\AsAction;

class SupplierProductRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(SupplierProduct $supplierProduct): void
    {
        if ($supplierProduct->trashed()) {
            return;
        }

        $supplierProduct->universalSearch()->updateOrCreate(
            [],
            [
                'group_id' => $supplierProduct->group_id,
                'sections' => ['supply-chain'],
                'haystack_tier_1' => trim($supplierProduct->code.' '.$supplierProduct->name),
            ]
        );
    }
}
