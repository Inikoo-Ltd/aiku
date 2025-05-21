<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:11:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $outbox_id
 * @property string $parent_type MailShot|EmailBulkRun|EmailPush|EmailOngoingRun
 * @property int $parent_id
 * @property int|null $email_address_id
 * @property DispatchedEmailProviderEnum $provider
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
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $source_id
 * @property int $number_email_tracking_events
 * @property int|null $post_room_id
 * @property int|null $org_post_room_id
 * @property-read \App\Models\Comms\EmailAddress|null $emailAddress
 * @property-read \App\Models\Comms\EmailCopy|null $emailCopy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comms\EmailTrackingEvent> $emailTrackingEvents
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Comms\Mailshot|null $mailshot
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Comms\Outbox|null $outbox
 * @property-read Model|\Eloquent $parent
 * @property-read Model|\Eloquent|null $recipient
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispatchedEmail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispatchedEmail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DispatchedEmail query()
 * @mixin \Eloquent
 */
class DispatchedEmail extends Model
{
    use InShop;

    protected $casts = [
        'data'             => 'array',
        'state'            => DispatchedEmailStateEnum::class,
        'provider'         => DispatchedEmailProviderEnum::class,
        'sent_at'          => 'datetime',
        'first_read_at'    => 'datetime',
        'last_read_at'     => 'datetime',
        'first_clicked_at' => 'datetime',
        'last_clicked_at'  => 'datetime',
        'fetched_at'       => 'datetime',
        'last_fetched_at'  => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function emailAddress(): BelongsTo
    {
        return $this->belongsTo(EmailAddress::class);
    }


    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }


    public function mailshot(): BelongsTo
    {
        return $this->belongsTo(Mailshot::class);
    }

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(Outbox::class);
    }

    public function getName(): string
    {
        if ($this->is_test) {
            return Auth::user()->contact_name;
        }

        if ($this->recipient) {
            /** @var Prospect|Customer $recipient */
            $recipient = $this->recipient;

            return $recipient->name;
        }

        return '';
    }

    public function emailTrackingEvents(): HasMany
    {
        return $this->hasMany(EmailTrackingEvent::class);
    }

    public function emailCopy(): HasOne
    {
        return $this->hasOne(EmailCopy::class);
    }

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }


}
