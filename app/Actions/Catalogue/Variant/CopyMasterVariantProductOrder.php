<?php

/*
 * Author: Louis Perez Napitupulu
 * Created: Wed, 07 Oct 2026 09:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Catalogue\Variant;

use App\Models\Catalogue\Variant;
use App\Models\Masters\MasterVariant;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class CopyMasterVariantProductOrder
{
    use AsObject;

    /**
     * Shop variants that follow their master take the master's product order, matched through each product's master.
     */
    public function handle(MasterVariant|Variant $source): int
    {
        $variantColumn = $source instanceof MasterVariant ? 'master_variant_id' : 'id';

        return DB::update(
            "update products set index_under_variant = master_assets.index_under_master_variant
             from variants, master_assets
             where products.variant_id = variants.id
               and products.master_product_id = master_assets.id
               and variants.follow_master_variant_order
               and variants.$variantColumn = ?",
            [$source->id]
        );
    }
}
