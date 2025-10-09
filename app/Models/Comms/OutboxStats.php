<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:11:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $outbox_id
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
 * @property int $number_subscribed_user
 * @property int $number_subscribed_external_emails
 * @property-read \App\Models\Comms\Outbox|null $outbox
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutboxStats query()
 * @mixin \Eloquent
 */
class OutboxStats extends Model
{
    protected $table = 'outbox_stats';

    protected $guarded = [];

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(Outbox::class);
    }
}
