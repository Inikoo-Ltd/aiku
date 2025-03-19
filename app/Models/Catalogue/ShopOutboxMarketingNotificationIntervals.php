<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Dec 2024 23:23:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $shop_id
 * @property int $marketing_notification_dispatched_emails_all
 * @property int $marketing_notification_dispatched_emails_1y
 * @property int $marketing_notification_dispatched_emails_1q
 * @property int $marketing_notification_dispatched_emails_1m
 * @property int $marketing_notification_dispatched_emails_1w
 * @property int $marketing_notification_dispatched_emails_3d
 * @property int $marketing_notification_dispatched_emails_1d
 * @property int $marketing_notification_dispatched_emails_ytd
 * @property int $marketing_notification_dispatched_emails_qtd
 * @property int $marketing_notification_dispatched_emails_mtd
 * @property int $marketing_notification_dispatched_emails_wtd
 * @property int $marketing_notification_dispatched_emails_tdy
 * @property int $marketing_notification_dispatched_emails_lm
 * @property int $marketing_notification_dispatched_emails_lw
 * @property int $marketing_notification_dispatched_emails_ld
 * @property int $marketing_notification_dispatched_emails_1y_ly
 * @property int $marketing_notification_dispatched_emails_1q_ly
 * @property int $marketing_notification_dispatched_emails_1m_ly
 * @property int $marketing_notification_dispatched_emails_1w_ly
 * @property int $marketing_notification_dispatched_emails_3d_ly
 * @property int $marketing_notification_dispatched_emails_1d_ly
 * @property int $marketing_notification_dispatched_emails_ytd_ly
 * @property int $marketing_notification_dispatched_emails_qtd_ly
 * @property int $marketing_notification_dispatched_emails_mtd_ly
 * @property int $marketing_notification_dispatched_emails_wtd_ly
 * @property int $marketing_notification_dispatched_emails_tdy_ly
 * @property int $marketing_notification_dispatched_emails_lm_ly
 * @property int $marketing_notification_dispatched_emails_lw_ly
 * @property int $marketing_notification_dispatched_emails_ld_ly
 * @property int $marketing_notification_dispatched_emails_py1
 * @property int $marketing_notification_dispatched_emails_py2
 * @property int $marketing_notification_dispatched_emails_py3
 * @property int $marketing_notification_dispatched_emails_py4
 * @property int $marketing_notification_dispatched_emails_py5
 * @property int $marketing_notification_dispatched_emails_pq1
 * @property int $marketing_notification_dispatched_emails_pq2
 * @property int $marketing_notification_dispatched_emails_pq3
 * @property int $marketing_notification_dispatched_emails_pq4
 * @property int $marketing_notification_dispatched_emails_pq5
 * @property int $marketing_notification_opened_emails_all
 * @property int $marketing_notification_opened_emails_1y
 * @property int $marketing_notification_opened_emails_1q
 * @property int $marketing_notification_opened_emails_1m
 * @property int $marketing_notification_opened_emails_1w
 * @property int $marketing_notification_opened_emails_3d
 * @property int $marketing_notification_opened_emails_1d
 * @property int $marketing_notification_opened_emails_ytd
 * @property int $marketing_notification_opened_emails_qtd
 * @property int $marketing_notification_opened_emails_mtd
 * @property int $marketing_notification_opened_emails_wtd
 * @property int $marketing_notification_opened_emails_tdy
 * @property int $marketing_notification_opened_emails_lm
 * @property int $marketing_notification_opened_emails_lw
 * @property int $marketing_notification_opened_emails_ld
 * @property int $marketing_notification_opened_emails_1y_ly
 * @property int $marketing_notification_opened_emails_1q_ly
 * @property int $marketing_notification_opened_emails_1m_ly
 * @property int $marketing_notification_opened_emails_1w_ly
 * @property int $marketing_notification_opened_emails_3d_ly
 * @property int $marketing_notification_opened_emails_1d_ly
 * @property int $marketing_notification_opened_emails_ytd_ly
 * @property int $marketing_notification_opened_emails_qtd_ly
 * @property int $marketing_notification_opened_emails_mtd_ly
 * @property int $marketing_notification_opened_emails_wtd_ly
 * @property int $marketing_notification_opened_emails_tdy_ly
 * @property int $marketing_notification_opened_emails_lm_ly
 * @property int $marketing_notification_opened_emails_lw_ly
 * @property int $marketing_notification_opened_emails_ld_ly
 * @property int $marketing_notification_opened_emails_py1
 * @property int $marketing_notification_opened_emails_py2
 * @property int $marketing_notification_opened_emails_py3
 * @property int $marketing_notification_opened_emails_py4
 * @property int $marketing_notification_opened_emails_py5
 * @property int $marketing_notification_opened_emails_pq1
 * @property int $marketing_notification_opened_emails_pq2
 * @property int $marketing_notification_opened_emails_pq3
 * @property int $marketing_notification_opened_emails_pq4
 * @property int $marketing_notification_opened_emails_pq5
 * @property int $marketing_notification_clicked_emails_all
 * @property int $marketing_notification_clicked_emails_1y
 * @property int $marketing_notification_clicked_emails_1q
 * @property int $marketing_notification_clicked_emails_1m
 * @property int $marketing_notification_clicked_emails_1w
 * @property int $marketing_notification_clicked_emails_3d
 * @property int $marketing_notification_clicked_emails_1d
 * @property int $marketing_notification_clicked_emails_ytd
 * @property int $marketing_notification_clicked_emails_qtd
 * @property int $marketing_notification_clicked_emails_mtd
 * @property int $marketing_notification_clicked_emails_wtd
 * @property int $marketing_notification_clicked_emails_tdy
 * @property int $marketing_notification_clicked_emails_lm
 * @property int $marketing_notification_clicked_emails_lw
 * @property int $marketing_notification_clicked_emails_ld
 * @property int $marketing_notification_clicked_emails_1y_ly
 * @property int $marketing_notification_clicked_emails_1q_ly
 * @property int $marketing_notification_clicked_emails_1m_ly
 * @property int $marketing_notification_clicked_emails_1w_ly
 * @property int $marketing_notification_clicked_emails_3d_ly
 * @property int $marketing_notification_clicked_emails_1d_ly
 * @property int $marketing_notification_clicked_emails_ytd_ly
 * @property int $marketing_notification_clicked_emails_qtd_ly
 * @property int $marketing_notification_clicked_emails_mtd_ly
 * @property int $marketing_notification_clicked_emails_wtd_ly
 * @property int $marketing_notification_clicked_emails_tdy_ly
 * @property int $marketing_notification_clicked_emails_lm_ly
 * @property int $marketing_notification_clicked_emails_lw_ly
 * @property int $marketing_notification_clicked_emails_ld_ly
 * @property int $marketing_notification_clicked_emails_py1
 * @property int $marketing_notification_clicked_emails_py2
 * @property int $marketing_notification_clicked_emails_py3
 * @property int $marketing_notification_clicked_emails_py4
 * @property int $marketing_notification_clicked_emails_py5
 * @property int $marketing_notification_clicked_emails_pq1
 * @property int $marketing_notification_clicked_emails_pq2
 * @property int $marketing_notification_clicked_emails_pq3
 * @property int $marketing_notification_clicked_emails_pq4
 * @property int $marketing_notification_clicked_emails_pq5
 * @property int $marketing_notification_bounced_emails_all
 * @property int $marketing_notification_bounced_emails_1y
 * @property int $marketing_notification_bounced_emails_1q
 * @property int $marketing_notification_bounced_emails_1m
 * @property int $marketing_notification_bounced_emails_1w
 * @property int $marketing_notification_bounced_emails_3d
 * @property int $marketing_notification_bounced_emails_1d
 * @property int $marketing_notification_bounced_emails_ytd
 * @property int $marketing_notification_bounced_emails_qtd
 * @property int $marketing_notification_bounced_emails_mtd
 * @property int $marketing_notification_bounced_emails_wtd
 * @property int $marketing_notification_bounced_emails_tdy
 * @property int $marketing_notification_bounced_emails_lm
 * @property int $marketing_notification_bounced_emails_lw
 * @property int $marketing_notification_bounced_emails_ld
 * @property int $marketing_notification_bounced_emails_1y_ly
 * @property int $marketing_notification_bounced_emails_1q_ly
 * @property int $marketing_notification_bounced_emails_1m_ly
 * @property int $marketing_notification_bounced_emails_1w_ly
 * @property int $marketing_notification_bounced_emails_3d_ly
 * @property int $marketing_notification_bounced_emails_1d_ly
 * @property int $marketing_notification_bounced_emails_ytd_ly
 * @property int $marketing_notification_bounced_emails_qtd_ly
 * @property int $marketing_notification_bounced_emails_mtd_ly
 * @property int $marketing_notification_bounced_emails_wtd_ly
 * @property int $marketing_notification_bounced_emails_tdy_ly
 * @property int $marketing_notification_bounced_emails_lm_ly
 * @property int $marketing_notification_bounced_emails_lw_ly
 * @property int $marketing_notification_bounced_emails_ld_ly
 * @property int $marketing_notification_bounced_emails_py1
 * @property int $marketing_notification_bounced_emails_py2
 * @property int $marketing_notification_bounced_emails_py3
 * @property int $marketing_notification_bounced_emails_py4
 * @property int $marketing_notification_bounced_emails_py5
 * @property int $marketing_notification_bounced_emails_pq1
 * @property int $marketing_notification_bounced_emails_pq2
 * @property int $marketing_notification_bounced_emails_pq3
 * @property int $marketing_notification_bounced_emails_pq4
 * @property int $marketing_notification_bounced_emails_pq5
 * @property int $marketing_notification_subscribed_all
 * @property int $marketing_notification_subscribed_1y
 * @property int $marketing_notification_subscribed_1q
 * @property int $marketing_notification_subscribed_1m
 * @property int $marketing_notification_subscribed_1w
 * @property int $marketing_notification_subscribed_3d
 * @property int $marketing_notification_subscribed_1d
 * @property int $marketing_notification_subscribed_ytd
 * @property int $marketing_notification_subscribed_qtd
 * @property int $marketing_notification_subscribed_mtd
 * @property int $marketing_notification_subscribed_wtd
 * @property int $marketing_notification_subscribed_tdy
 * @property int $marketing_notification_subscribed_lm
 * @property int $marketing_notification_subscribed_lw
 * @property int $marketing_notification_subscribed_ld
 * @property int $marketing_notification_subscribed_1y_ly
 * @property int $marketing_notification_subscribed_1q_ly
 * @property int $marketing_notification_subscribed_1m_ly
 * @property int $marketing_notification_subscribed_1w_ly
 * @property int $marketing_notification_subscribed_3d_ly
 * @property int $marketing_notification_subscribed_1d_ly
 * @property int $marketing_notification_subscribed_ytd_ly
 * @property int $marketing_notification_subscribed_qtd_ly
 * @property int $marketing_notification_subscribed_mtd_ly
 * @property int $marketing_notification_subscribed_wtd_ly
 * @property int $marketing_notification_subscribed_tdy_ly
 * @property int $marketing_notification_subscribed_lm_ly
 * @property int $marketing_notification_subscribed_lw_ly
 * @property int $marketing_notification_subscribed_ld_ly
 * @property int $marketing_notification_subscribed_py1
 * @property int $marketing_notification_subscribed_py2
 * @property int $marketing_notification_subscribed_py3
 * @property int $marketing_notification_subscribed_py4
 * @property int $marketing_notification_subscribed_py5
 * @property int $marketing_notification_subscribed_pq1
 * @property int $marketing_notification_subscribed_pq2
 * @property int $marketing_notification_subscribed_pq3
 * @property int $marketing_notification_subscribed_pq4
 * @property int $marketing_notification_subscribed_pq5
 * @property int $marketing_notification_unsubscribed_all
 * @property int $marketing_notification_unsubscribed_1y
 * @property int $marketing_notification_unsubscribed_1q
 * @property int $marketing_notification_unsubscribed_1m
 * @property int $marketing_notification_unsubscribed_1w
 * @property int $marketing_notification_unsubscribed_3d
 * @property int $marketing_notification_unsubscribed_1d
 * @property int $marketing_notification_unsubscribed_ytd
 * @property int $marketing_notification_unsubscribed_qtd
 * @property int $marketing_notification_unsubscribed_mtd
 * @property int $marketing_notification_unsubscribed_wtd
 * @property int $marketing_notification_unsubscribed_tdy
 * @property int $marketing_notification_unsubscribed_lm
 * @property int $marketing_notification_unsubscribed_lw
 * @property int $marketing_notification_unsubscribed_ld
 * @property int $marketing_notification_unsubscribed_1y_ly
 * @property int $marketing_notification_unsubscribed_1q_ly
 * @property int $marketing_notification_unsubscribed_1m_ly
 * @property int $marketing_notification_unsubscribed_1w_ly
 * @property int $marketing_notification_unsubscribed_3d_ly
 * @property int $marketing_notification_unsubscribed_1d_ly
 * @property int $marketing_notification_unsubscribed_ytd_ly
 * @property int $marketing_notification_unsubscribed_qtd_ly
 * @property int $marketing_notification_unsubscribed_mtd_ly
 * @property int $marketing_notification_unsubscribed_wtd_ly
 * @property int $marketing_notification_unsubscribed_tdy_ly
 * @property int $marketing_notification_unsubscribed_lm_ly
 * @property int $marketing_notification_unsubscribed_lw_ly
 * @property int $marketing_notification_unsubscribed_ld_ly
 * @property int $marketing_notification_unsubscribed_py1
 * @property int $marketing_notification_unsubscribed_py2
 * @property int $marketing_notification_unsubscribed_py3
 * @property int $marketing_notification_unsubscribed_py4
 * @property int $marketing_notification_unsubscribed_py5
 * @property int $marketing_notification_unsubscribed_pq1
 * @property int $marketing_notification_unsubscribed_pq2
 * @property int $marketing_notification_unsubscribed_pq3
 * @property int $marketing_notification_unsubscribed_pq4
 * @property int $marketing_notification_unsubscribed_pq5
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopOutboxMarketingNotificationIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopOutboxMarketingNotificationIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopOutboxMarketingNotificationIntervals query()
 * @mixin \Eloquent
 */
class ShopOutboxMarketingNotificationIntervals extends Model
{
    protected $table = 'shop_outbox_marketing_notification_intervals';

    protected $guarded = [];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
