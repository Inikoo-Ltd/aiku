<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:11:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Actions\Utils\Abbreviate;
use App\Enums\Comms\Outbox\OutboxBuilderEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Traits\InShop;
use App\Models\Web\Website;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Comms\Outbox
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $post_room_id
 * @property int|null $org_post_room_id
 * @property int|null $shop_id
 * @property int|null $website_id
 * @property int|null $fulfilment_id
 * @property string $slug
 * @property OutboxCodeEnum $code
 * @property OutboxTypeEnum $type
 * @property string|null $model_type
 * @property int|null $model_id
 * @property string $name
 * @property OutboxBuilderEnum|null $builder current default builder for future emails
 * @property OutboxStateEnum $state
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property array<array-key, mixed> $sources
 * @property bool $is_applicable
 * @property-read Collection<int, \App\Models\Comms\DispatchedEmail> $dispatchedEmails
 * @property-read Collection<int, \App\Models\Comms\EmailBulkRun> $emailBulkRuns
 * @property-read \App\Models\Comms\EmailOngoingRun|null $emailOngoingRun
 * @property-read \App\Models\Comms\EmailTemplate|null $emailTemplate
 * @property-read Collection<int, \App\Models\Comms\Email> $emails
 * @property-read Fulfilment|null $fulfilment
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Comms\OutboxIntervals|null $intervals
 * @property-read Collection<int, \App\Models\Comms\Mailshot> $mailshots
 * @property-read \App\Models\Comms\OrgPostRoom|null $orgPostRoom
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Comms\PostRoom|null $postRoom
 * @property-read Shop|null $shop
 * @property-read \App\Models\Comms\OutboxStats|null $stats
 * @property-read Collection<int, \App\Models\Comms\OutBoxHasSubscriber> $subscribedUsers
 * @property-read Collection<int, \App\Models\Comms\OutboxTimeSeries> $timeSeries
 * @property-read Website|null $website
 * @method static \Database\Factories\Comms\OutboxFactory factory($count = null, $state = [])
 * @method static Builder<static>|Outbox newModelQuery()
 * @method static Builder<static>|Outbox newQuery()
 * @method static Builder<static>|Outbox onlyTrashed()
 * @method static Builder<static>|Outbox query()
 * @method static Builder<static>|Outbox withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Outbox withoutTrashed()
 * @mixin Eloquent
 */
class Outbox extends Model
{
    use SoftDeletes;
    use HasSlug;
    use HasFactory;
    use InShop;

    protected $table = 'outboxes';

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $casts = [
        'data'    => 'array',
        'sources' => 'array',
        'code'    => OutboxCodeEnum::class,
        'type'    => OutboxTypeEnum::class,
        'state'   => OutboxStateEnum::class,
        'builder' => OutboxBuilderEnum::class
    ];

    protected $attributes = [
        'data'    => '{}',
        'sources' => '{}'
    ];

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                if ($this->type == 'reorder-reminder') {
                    $abbreviation = 'ror';
                } else {
                    $abbreviation = Abbreviate::run(string: $this->code->value, maximumLength: 16);
                }
                if ($this->shop_id) {
                    $abbreviation .= ' '.$this->shop->slug;
                }
                if ($this->fulfilment_id) {
                    $abbreviation .= ' '.$this->fulfilment->slug;
                }
                if ($this->website_id) {
                    $abbreviation .= ' '.$this->website->slug;
                } else {
                    $abbreviation .= ' '.$this->organisation->slug;
                }

                return $abbreviation;
            })
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }


    public function stats(): HasOne
    {
        return $this->hasOne(OutboxStats::class);
    }

    public function intervals(): HasOne
    {
        return $this->hasOne(OutboxIntervals::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    public function emailBulkRuns(): HasMany
    {
        return $this->hasMany(EmailBulkRun::class);
    }

    public function emailOngoingRun(): HasOne
    {
        return $this->hasOne(EmailOngoingRun::class);
    }

    public function mailshots(): HasMany
    {
        return $this->hasMany(Mailshot::class);
    }

    public function dispatchedEmails(): HasMany
    {
        return $this->hasMany(DispatchedEmail::class);
    }

    public function emailTemplate(): HasOne
    {
        return $this->hasOne(EmailTemplate::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function fulfilment(): BelongsTo
    {
        return $this->belongsTo(Fulfilment::class);
    }

    public function postRoom(): BelongsTo
    {
        return $this->belongsTo(PostRoom::class);
    }

    public function orgPostRoom(): BelongsTo
    {
        return $this->belongsTo(OrgPostRoom::class);
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(OutboxTimeSeries::class);
    }

    public function subscribedUsers(): HasMany
    {
        return $this->hasMany(OutBoxHasSubscriber::class);
    }

}
