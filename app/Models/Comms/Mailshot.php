<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:11:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Actions\Utils\Abbreviate;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Comms\Mailshot
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property string $slug
 * @property string $subject
 * @property int|null $outbox_id
 * @property int|null $email_id
 * @property MailshotStateEnum $state
 * @property MailshotTypeEnum $type
 * @property \Illuminate\Support\Carbon $date
 * @property \Illuminate\Support\Carbon|null $ready_at
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property string|null $recipients_stored_at
 * @property \Illuminate\Support\Carbon|null $start_sending_at
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $stopped_at
 * @property array<array-key, mixed> $recipients_recipe
 * @property int|null $publisher_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property string|null $source_id
 * @property string|null $source_alt_id
 * @property string|null $source_alt2_id
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Collection<int, \App\Models\Comms\EmailDeliveryChannel> $channels
 * @property-read Collection<int, \App\Models\Comms\DispatchedEmail> $dispatchedEmails
 * @property-read \App\Models\Comms\Email|null $email
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Comms\Outbox|null $outbox
 * @property-read Model|\Eloquent $parent
 * @property-read Collection<int, \App\Models\Comms\MailshotRecipient> $recipients
 * @property-read Shop|null $shop
 * @property-read \App\Models\Comms\MailshotStats|null $stats
 * @method static \Database\Factories\Comms\MailshotFactory factory($count = null, $state = [])
 * @method static Builder<static>|Mailshot newModelQuery()
 * @method static Builder<static>|Mailshot newQuery()
 * @method static Builder<static>|Mailshot onlyTrashed()
 * @method static Builder<static>|Mailshot query()
 * @method static Builder<static>|Mailshot withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Mailshot withoutTrashed()
 * @mixin Eloquent
 */
class Mailshot extends Model implements Auditable
{
    use SoftDeletes;
    use HasFactory;
    use InShop;
    use HasHistory;
    use HasSlug;

    protected $casts = [
        'recipients_recipe' => 'array',
        'data'              => 'array',
        'type'              => MailshotTypeEnum::class,
        'state'             => MailshotStateEnum::class,
        'date'              => 'datetime',
        'ready_at'          => 'datetime',
        'scheduled_at'      => 'datetime',
        'start_sending_at'  => 'datetime',
        'sent_at'           => 'datetime',
        'cancelled_at'      => 'datetime',
        'stopped_at'        => 'datetime',
    ];

    protected $attributes = [
        'data'              => '{}',
        'recipients_recipe' => '{}',
    ];


    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'marketing'
        ];
    }

    protected array $auditInclude = [
        'subject',
        'schedule_at',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return Abbreviate::run(string: $this->type->value, maximumLength: 4).
                    ' '.$this->shop->code.
                    ' '.$this->date->format('Y-m-d');
            })
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MailshotRecipient::class);
    }

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(Outbox::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(MailshotStats::class);
    }

    public function dispatchedEmails(): MorphMany
    {
        return $this->morphMany(DispatchedEmail::class, 'parent');
    }

    public function email(): MorphOne
    {
        return $this->morphOne(Email::class, 'parent');
    }

    public function sender()
    {
        if (app()->environment('production')) {
            /** @var Shop $parent */
            $parent = $this->parent;
            //todo we need to set up sender and very SES etc
            //   $sender = $parent->senderEmail->email_address;
            $sender = $parent->email;
        } else {
            $sender = config('app.email_address_in_non_production_env');
        }

        return $sender;
    }

    public function channels(): MorphMany
    {
        return $this->morphMany(EmailDeliveryChannel::class, 'model');
    }



}
