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
        return DB::raw("(SELECT COALESCE(NULLIF(variants.option_translations->>(variants.data->'variants'->0->>'label'), ''), variants.data->'variants'->0->>'label') FROM variants WHERE variants.id = products.variant_id) as variant_axis_label");
    }

    public function getVariantOptionLabelColumn(): Expression
    {
        return DB::raw("(SELECT variants.data->'products'->(products.id::text)->>(variants.data->'variants'->0->>'label') FROM variants WHERE variants.id = products.variant_id) as variant_option_label");
    }

    public function getVariantSizeOptionsColumn(): Expression
    {
        return DB::raw("(SELECT jsonb_agg(jsonb_build_object('code', variant_options.code, 'unit', variant_options.unit, 'units', variant_options.units, 'option', variants.data->'products'->(variant_options.id::text)->>(variants.data->'variants'->0->>'label'))) FROM products AS variant_options JOIN variants ON variants.id = variant_options.variant_id WHERE products.is_variant_leader AND variant_options.variant_id = products.variant_id AND variant_options.is_for_sale AND variant_options.deleted_at IS NULL) as variant_size_options");
    }

    public function getVariantTitleColumn(): Expression
    {
        return DB::raw("(SELECT COALESCE(NULLIF(variants.label, ''), leaders.name) FROM variants LEFT JOIN products AS leaders ON leaders.id = variants.leader_id WHERE variants.id = products.variant_id) as variant_title");
    }
}
