<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 12 Feb 2026 11:03:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Models\Comms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $email_bulk_run_id
 * @property int $dispatched_email_id
 * @property string $recipient_type
 * @property int $recipient_id
 * @property int $channel
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Comms\DispatchedEmail|null $dispatchedEmail
 * @property-read \App\Models\Comms\EmailBulkRun $emailBulkRun
 * @property-read Model|\Eloquent $recipient
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailBulkRunRecipient newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailBulkRunRecipient newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailBulkRunRecipient query()
 * @mixin \Eloquent
 */
class EmailBulkRunRecipient extends Model
{
    protected $guarded = [];

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    public function emailBulkRun(): BelongsTo
    {
        return $this->belongsTo(EmailBulkRun::class);
    }

    public function dispatchedEmail(): BelongsTo
    {
        return $this->belongsTo(DispatchedEmail::class);
    }
}
