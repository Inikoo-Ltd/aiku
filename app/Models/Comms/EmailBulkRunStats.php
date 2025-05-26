<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Nov 2024 10:35:14 Central Indonesia Time, Sanur, Kuta, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int|null $email_bulk_run_id
 * @property int $number_dispatched_emails
 * @property int $number_dispatched_emails_state_ready
 * @property int $number_dispatched_emails_state_sent_to_provider
 * @property int $number_dispatched_emails_state_error
 * @property int $number_dispatched_emails_state_rejected_by_provider
 * @property int $number_dispatched_emails_state_sent
 * @property int $number_dispatched_emails_state_delivered
 * @property int $number_dispatched_emails_state_hard_bounce
 * @property int $number_dispatched_emails_state_soft_bounce
 * @property int $number_dispatched_emails_state_opened
 * @property int $number_dispatched_emails_state_clicked
 * @property int $number_dispatched_emails_state_spam
 * @property int $number_dispatched_emails_state_unsubscribed
 * @property int $number_provoked_unsubscribe
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_dispatched_emails_state_delay
 * @property int $number_try_send_failure
 * @property int $number_try_send_success
 * @property int $number_try_send_total
 * @property int $number_deliveries_failure
 * @property int $number_deliveries_success
 * @property int $number_delivered_open_failure
 * @property int $number_delivered_open_success
 * @property int $number_opened_interact_failure
 * @property int $number_opened_interact_success
 * @property-read \App\Models\Comms\EmailBulkRun|null $emailBulkRun
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailBulkRunStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailBulkRunStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailBulkRunStats query()
 * @mixin \Eloquent
 */
class EmailBulkRunStats extends Model
{
    protected $table = 'email_bulk_run_stats';

    protected $guarded = [];

    public function emailBulkRun(): BelongsTo
    {
        return $this->belongsTo(EmailBulkRun::class);
    }
}
