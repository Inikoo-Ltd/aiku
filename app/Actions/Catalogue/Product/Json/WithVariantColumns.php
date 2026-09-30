<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 30 Sep 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Json;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

trait WithVariantColumns
{
    public function getVariantAxisLabelColumn(): Expression
    {
        return DB::raw("(SELECT variants.data->'variants'->0->>'label' FROM variants WHERE variants.id = products.variant_id) as variant_axis_label");
    }

    public function getVariantTitleColumn(): Expression
    {
        return DB::raw("(SELECT COALESCE(NULLIF(variants.label, ''), leaders.name) FROM variants LEFT JOIN products AS leaders ON leaders.id = variants.leader_id WHERE variants.id = products.variant_id) as variant_title");
    }
}
