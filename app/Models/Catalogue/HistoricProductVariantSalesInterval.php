<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Jun 2024 19:57:52 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $historic_product_variant_id
 * @property string $shop_amount_all
 * @property string $shop_amount_1y
 * @property string $shop_amount_1q
 * @property string $shop_amount_1m
 * @property string $shop_amount_1w
 * @property string $shop_amount_ytd
 * @property string $shop_amount_qtd
 * @property string $shop_amount_mtd
 * @property string $shop_amount_wtd
 * @property string $shop_amount_lm
 * @property string $shop_amount_lw
 * @property string $shop_amount_yda
 * @property string $shop_amount_tdy
 * @property string $shop_amount_all_ly
 * @property string $shop_amount_1y_ly
 * @property string $shop_amount_1q_ly
 * @property string $shop_amount_1m_ly
 * @property string $shop_amount_1w_ly
 * @property string $shop_amount_ytd_ly
 * @property string $shop_amount_qtd_ly
 * @property string $shop_amount_mtd_ly
 * @property string $shop_amount_wtd_ly
 * @property string $shop_amount_lm_ly
 * @property string $shop_amount_lw_ly
 * @property string $shop_amount_yda_ly
 * @property string $shop_amount_tdy_ly
 * @property string $shop_amount_py1
 * @property string $shop_amount_py2
 * @property string $shop_amount_py3
 * @property string $shop_amount_py4
 * @property string $shop_amount_py5
 * @property string $shop_amount_pq1
 * @property string $shop_amount_pq2
 * @property string $shop_amount_pq3
 * @property string $shop_amount_pq4
 * @property string $shop_amount_pq5
 * @property string $org_amount_all
 * @property string $org_amount_1y
 * @property string $org_amount_1q
 * @property string $org_amount_1m
 * @property string $org_amount_1w
 * @property string $org_amount_ytd
 * @property string $org_amount_qtd
 * @property string $org_amount_mtd
 * @property string $org_amount_wtd
 * @property string $org_amount_lm
 * @property string $org_amount_lw
 * @property string $org_amount_yda
 * @property string $org_amount_tdy
 * @property string $org_amount_all_ly
 * @property string $org_amount_1y_ly
 * @property string $org_amount_1q_ly
 * @property string $org_amount_1m_ly
 * @property string $org_amount_1w_ly
 * @property string $org_amount_ytd_ly
 * @property string $org_amount_qtd_ly
 * @property string $org_amount_mtd_ly
 * @property string $org_amount_wtd_ly
 * @property string $org_amount_lm_ly
 * @property string $org_amount_lw_ly
 * @property string $org_amount_yda_ly
 * @property string $org_amount_tdy_ly
 * @property string $org_amount_py1
 * @property string $org_amount_py2
 * @property string $org_amount_py3
 * @property string $org_amount_py4
 * @property string $org_amount_py5
 * @property string $org_amount_pq1
 * @property string $org_amount_pq2
 * @property string $org_amount_pq3
 * @property string $org_amount_pq4
 * @property string $org_amount_pq5
 * @property string $group_amount_all
 * @property string $group_amount_1y
 * @property string $group_amount_1q
 * @property string $group_amount_1m
 * @property string $group_amount_1w
 * @property string $group_amount_ytd
 * @property string $group_amount_qtd
 * @property string $group_amount_mtd
 * @property string $group_amount_wtd
 * @property string $group_amount_lm
 * @property string $group_amount_lw
 * @property string $group_amount_yda
 * @property string $group_amount_tdy
 * @property string $group_amount_all_ly
 * @property string $group_amount_1y_ly
 * @property string $group_amount_1q_ly
 * @property string $group_amount_1m_ly
 * @property string $group_amount_1w_ly
 * @property string $group_amount_ytd_ly
 * @property string $group_amount_qtd_ly
 * @property string $group_amount_mtd_ly
 * @property string $group_amount_wtd_ly
 * @property string $group_amount_lm_ly
 * @property string $group_amount_lw_ly
 * @property string $group_amount_yda_ly
 * @property string $group_amount_tdy_ly
 * @property string $group_amount_py1
 * @property string $group_amount_py2
 * @property string $group_amount_py3
 * @property string $group_amount_py4
 * @property string $group_amount_py5
 * @property string $group_amount_pq1
 * @property string $group_amount_pq2
 * @property string $group_amount_pq3
 * @property string $group_amount_pq4
 * @property string $group_amount_pq5
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Catalogue\HistoricProductVariant $historicProductVariant
 * @method static \Illuminate\Database\Eloquent\Builder|HistoricProductVariantSalesInterval newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoricProductVariantSalesInterval newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|HistoricProductVariantSalesInterval query()
 * @mixin \Eloquent
 */
class HistoricProductVariantSalesInterval extends Model
{
    protected $guarded = [];

    public function historicProductVariant(): BelongsTo
    {
        return $this->belongsTo(HistoricProductVariant::class);
    }
}
