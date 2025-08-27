<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Dec 2024 16:13:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $shop_id
 * @property int $newsletters_all
 * @property int $newsletters_1y
 * @property int $newsletters_1q
 * @property int $newsletters_1m
 * @property int $newsletters_1w
 * @property int $newsletters_3d
 * @property int $newsletters_1d
 * @property int $newsletters_ytd
 * @property int $newsletters_qtd
 * @property int $newsletters_mtd
 * @property int $newsletters_wtd
 * @property int $newsletters_tdy
 * @property int $newsletters_lm
 * @property int $newsletters_lw
 * @property int $newsletters_ld
 * @property int $newsletters_1y_ly
 * @property int $newsletters_1q_ly
 * @property int $newsletters_1m_ly
 * @property int $newsletters_1w_ly
 * @property int $newsletters_3d_ly
 * @property int $newsletters_1d_ly
 * @property int $newsletters_ytd_ly
 * @property int $newsletters_qtd_ly
 * @property int $newsletters_mtd_ly
 * @property int $newsletters_wtd_ly
 * @property int $newsletters_tdy_ly
 * @property int $newsletters_lm_ly
 * @property int $newsletters_lw_ly
 * @property int $newsletters_ld_ly
 * @property int $newsletters_py1
 * @property int $newsletters_py2
 * @property int $newsletters_py3
 * @property int $newsletters_py4
 * @property int $newsletters_py5
 * @property int $newsletters_pq1
 * @property int $newsletters_pq2
 * @property int $newsletters_pq3
 * @property int $newsletters_pq4
 * @property int $newsletters_pq5
 * @property int $marketing_emails_all
 * @property int $marketing_emails_1y
 * @property int $marketing_emails_1q
 * @property int $marketing_emails_1m
 * @property int $marketing_emails_1w
 * @property int $marketing_emails_3d
 * @property int $marketing_emails_1d
 * @property int $marketing_emails_ytd
 * @property int $marketing_emails_qtd
 * @property int $marketing_emails_mtd
 * @property int $marketing_emails_wtd
 * @property int $marketing_emails_tdy
 * @property int $marketing_emails_lm
 * @property int $marketing_emails_lw
 * @property int $marketing_emails_ld
 * @property int $marketing_emails_1y_ly
 * @property int $marketing_emails_1q_ly
 * @property int $marketing_emails_1m_ly
 * @property int $marketing_emails_1w_ly
 * @property int $marketing_emails_3d_ly
 * @property int $marketing_emails_1d_ly
 * @property int $marketing_emails_ytd_ly
 * @property int $marketing_emails_qtd_ly
 * @property int $marketing_emails_mtd_ly
 * @property int $marketing_emails_wtd_ly
 * @property int $marketing_emails_tdy_ly
 * @property int $marketing_emails_lm_ly
 * @property int $marketing_emails_lw_ly
 * @property int $marketing_emails_ld_ly
 * @property int $marketing_emails_py1
 * @property int $marketing_emails_py2
 * @property int $marketing_emails_py3
 * @property int $marketing_emails_py4
 * @property int $marketing_emails_py5
 * @property int $marketing_emails_pq1
 * @property int $marketing_emails_pq2
 * @property int $marketing_emails_pq3
 * @property int $marketing_emails_pq4
 * @property int $marketing_emails_pq5
 * @property int $abandoned_carts_all
 * @property int $abandoned_carts_1y
 * @property int $abandoned_carts_1q
 * @property int $abandoned_carts_1m
 * @property int $abandoned_carts_1w
 * @property int $abandoned_carts_3d
 * @property int $abandoned_carts_1d
 * @property int $abandoned_carts_ytd
 * @property int $abandoned_carts_qtd
 * @property int $abandoned_carts_mtd
 * @property int $abandoned_carts_wtd
 * @property int $abandoned_carts_tdy
 * @property int $abandoned_carts_lm
 * @property int $abandoned_carts_lw
 * @property int $abandoned_carts_ld
 * @property int $abandoned_carts_1y_ly
 * @property int $abandoned_carts_1q_ly
 * @property int $abandoned_carts_1m_ly
 * @property int $abandoned_carts_1w_ly
 * @property int $abandoned_carts_3d_ly
 * @property int $abandoned_carts_1d_ly
 * @property int $abandoned_carts_ytd_ly
 * @property int $abandoned_carts_qtd_ly
 * @property int $abandoned_carts_mtd_ly
 * @property int $abandoned_carts_wtd_ly
 * @property int $abandoned_carts_tdy_ly
 * @property int $abandoned_carts_lm_ly
 * @property int $abandoned_carts_lw_ly
 * @property int $abandoned_carts_ld_ly
 * @property int $abandoned_carts_py1
 * @property int $abandoned_carts_py2
 * @property int $abandoned_carts_py3
 * @property int $abandoned_carts_py4
 * @property int $abandoned_carts_py5
 * @property int $abandoned_carts_pq1
 * @property int $abandoned_carts_pq2
 * @property int $abandoned_carts_pq3
 * @property int $abandoned_carts_pq4
 * @property int $abandoned_carts_pq5
 * @property int $total_mailshots_all
 * @property int $total_mailshots_1y
 * @property int $total_mailshots_1q
 * @property int $total_mailshots_1m
 * @property int $total_mailshots_1w
 * @property int $total_mailshots_3d
 * @property int $total_mailshots_1d
 * @property int $total_mailshots_ytd
 * @property int $total_mailshots_qtd
 * @property int $total_mailshots_mtd
 * @property int $total_mailshots_wtd
 * @property int $total_mailshots_tdy
 * @property int $total_mailshots_lm
 * @property int $total_mailshots_lw
 * @property int $total_mailshots_ld
 * @property int $total_mailshots_1y_ly
 * @property int $total_mailshots_1q_ly
 * @property int $total_mailshots_1m_ly
 * @property int $total_mailshots_1w_ly
 * @property int $total_mailshots_3d_ly
 * @property int $total_mailshots_1d_ly
 * @property int $total_mailshots_ytd_ly
 * @property int $total_mailshots_qtd_ly
 * @property int $total_mailshots_mtd_ly
 * @property int $total_mailshots_wtd_ly
 * @property int $total_mailshots_tdy_ly
 * @property int $total_mailshots_lm_ly
 * @property int $total_mailshots_lw_ly
 * @property int $total_mailshots_ld_ly
 * @property int $total_mailshots_py1
 * @property int $total_mailshots_py2
 * @property int $total_mailshots_py3
 * @property int $total_mailshots_py4
 * @property int $total_mailshots_py5
 * @property int $total_mailshots_pq1
 * @property int $total_mailshots_pq2
 * @property int $total_mailshots_pq3
 * @property int $total_mailshots_pq4
 * @property int $total_mailshots_pq5
 * @property int $total_emails_all
 * @property int $total_emails_1y
 * @property int $total_emails_1q
 * @property int $total_emails_1m
 * @property int $total_emails_1w
 * @property int $total_emails_3d
 * @property int $total_emails_1d
 * @property int $total_emails_ytd
 * @property int $total_emails_qtd
 * @property int $total_emails_mtd
 * @property int $total_emails_wtd
 * @property int $total_emails_tdy
 * @property int $total_emails_lm
 * @property int $total_emails_lw
 * @property int $total_emails_ld
 * @property int $total_emails_1y_ly
 * @property int $total_emails_1q_ly
 * @property int $total_emails_1m_ly
 * @property int $total_emails_1w_ly
 * @property int $total_emails_3d_ly
 * @property int $total_emails_1d_ly
 * @property int $total_emails_ytd_ly
 * @property int $total_emails_qtd_ly
 * @property int $total_emails_mtd_ly
 * @property int $total_emails_wtd_ly
 * @property int $total_emails_tdy_ly
 * @property int $total_emails_lm_ly
 * @property int $total_emails_lw_ly
 * @property int $total_emails_ld_ly
 * @property int $total_emails_py1
 * @property int $total_emails_py2
 * @property int $total_emails_py3
 * @property int $total_emails_py4
 * @property int $total_emails_py5
 * @property int $total_emails_pq1
 * @property int $total_emails_pq2
 * @property int $total_emails_pq3
 * @property int $total_emails_pq4
 * @property int $total_emails_pq5
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Catalogue\Shop $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopMailshotsIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopMailshotsIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopMailshotsIntervals query()
 * @mixin \Eloquent
 */
class ShopMailshotsIntervals extends Model
{
    protected $table = 'shop_mailshots_intervals';

    protected $guarded = [];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
