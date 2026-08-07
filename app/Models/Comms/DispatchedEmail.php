<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:11:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int|null $outbox_id
 * @property int|null $email_address_id
 * @property DispatchedEmailStateEnum $state
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $first_read_at
 * @property \Illuminate\Support\Carbon|null $last_read_at
 * @property \Illuminate\Support\Carbon|null $first_clicked_at
 * @property \Illuminate\Support\Carbon|null $last_clicked_at
 * @property int $number_reads
 * @property int $number_clicks
 * @property bool $mask_as_spam
 * @property bool $provoked_unsubscribe
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $source_id
 * @property int $number_email_tracking_events
 * @property-read \App\Models\Comms\EmailAddress|null $emailAddress
 * @property-read \App\Models\Comms\EmailCopy|null $emailCopy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comms\EmailTrackingEvent> $emailTrackingEvents
 * @property-read \App\Models\Comms\Mailshot|null $mailshot
 * @property-read \App\Models\Comms\Outbox|null $outbox
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispatchedEmail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispatchedEmail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispatchedEmail query()
 * @mixin \Eloquent
 */
class DispatchedEmail extends Model
{
    protected $casts = [
        'data'             => 'array',
        'state'            => DispatchedEmailStateEnum::class,
        'sent_at'          => 'datetime',
        'first_read_at'    => 'datetime',
        'last_read_at'     => 'datetime',
        'first_clicked_at' => 'datetime',
        'last_clicked_at'  => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function emailAddress(): BelongsTo
    {
        return $this->belongsTo(EmailAddress::class);
    }

    /**
     * `dispatched_emails.mailshot_id` was dropped in May 2025, so this belongsTo resolves against a
     * column that no longer exists and always returns null. Kept only so existing callers keep
     * compiling; anything that needs the mailshot must use `sentMailshot()`.
     *
     * @deprecated Use sentMailshot()
     */
    public function mailshot(): BelongsTo
    {
        return $this->belongsTo(Mailshot::class);
    }

    /**
     * The mailshot this email was actually sent for, resolved through mailshot_recipients, which is
     * where the link has lived since the column was dropped. Null for transactional mail, which is
     * how a marketing touch is told apart from an order confirmation.
     */
    public function sentMailshot(): HasOneThrough
    {
        return $this->hasOneThrough(
            Mailshot::class,
            MailshotRecipient::class,
            'dispatched_email_id',
            'id',
            'id',
            'mailshot_id'
        );
    }

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(Outbox::class);
    }

    public function emailTrackingEvents(): HasMany
    {
        return $this->hasMany(EmailTrackingEvent::class);
    }

    public function emailCopy(): HasOne
    {
        return $this->hasOne(EmailCopy::class);
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_has_dispatched_emails');
    }

    public function prospects(): BelongsToMany
    {
        return $this->belongsToMany(Prospect::class, 'prospect_has_dispatched_emails');
    }
}
