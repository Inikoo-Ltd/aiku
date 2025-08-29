<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 08:14:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $email_ongoing_run_id
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
 * @property-read \App\Models\Comms\EmailOngoingRun|null $emailOngoingRun
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailOngoingRunStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailOngoingRunStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailOngoingRunStats query()
 * @mixin \Eloquent
 */
class EmailOngoingRunStats extends Model
{
    protected $table = 'email_ongoing_run_stats';

    protected $guarded = [];

    public function emailOngoingRun(): BelongsTo
    {
        return $this->belongsTo(EmailOngoingRun::class);
    }
}
