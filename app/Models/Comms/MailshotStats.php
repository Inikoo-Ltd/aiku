<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:11:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Comms\MailshotStats
 *
 * @property int $id
 * @property int|null $mailshot_id
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
 * @property int $number_deliveries_total
 * @property int $number_delivered_open_failure
 * @property int $number_delivered_open_success
 * @property int $number_opened_interact_failure
 * @property int $number_opened_interact_success
 * @property-read \App\Models\Comms\Mailshot|null $mailshot
 * @method static Builder<static>|MailshotStats newModelQuery()
 * @method static Builder<static>|MailshotStats newQuery()
 * @method static Builder<static>|MailshotStats query()
 * @mixin Eloquent
 */
class MailshotStats extends Model
{
    protected $table = 'mailshot_stats';

    protected $guarded = [];

    public function mailshot(): BelongsTo
    {
        return $this->belongsTo(Mailshot::class);
    }
}
