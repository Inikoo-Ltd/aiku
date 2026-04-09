<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:11:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $outbox_id
 * @property string|null $parent_type MailShot|EmailBulkRun|EmailPush|EmailOngoingRun
 * @property int|null $parent_id
 * @property int|null $email_address_id
 * @property string|null $provider
 * @property string|null $provider_dispatch_id
 * @property string|null $recipient_type
 * @property int|null $recipient_id
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
 * @property bool $is_test
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $source_id
 * @property int $number_email_tracking_events
 * @property int|null $post_room_id
 * @property int|null $org_post_room_id
 * @property string|null $uuid
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

    public function mailshot(): BelongsTo
    {
        return $this->belongsTo(Mailshot::class);
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


}
