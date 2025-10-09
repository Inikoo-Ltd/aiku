<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 29 Nov 2024 17:18:23 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Enums\Comms\OrgPostRoom\OrgPostRoomTypeEnum;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $post_room_id
 * @property string $slug
 * @property OrgPostRoomTypeEnum $type
 * @property string $name
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comms\EmailBulkRun> $emailBulkRuns
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Comms\OrgPostRoomIntervals|null $intervals
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comms\Outbox> $outboxes
 * @property-read \App\Models\Comms\PostRoom $postRoom
 * @property-read \App\Models\Comms\OrgPostRoomStats|null $stats
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPostRoom newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPostRoom newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPostRoom query()
 * @mixin \Eloquent
 */
class OrgPostRoom extends Model
{
    use HasSlug;
    use InOrganisation;

    protected $casts = [
        'type' => OrgPostRoomTypeEnum::class,
        'data' => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->type->value.'-'.$this->organisation->slug;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function stats(): HasOne
    {
        return $this->hasOne(OrgPostRoomStats::class);
    }

    public function intervals(): HasOne
    {
        return $this->hasOne(OrgPostRoomIntervals::class);
    }

    public function outboxes(): HasMany
    {
        return $this->hasMany(Outbox::class);
    }

    public function postRoom(): BelongsTo
    {
        return $this->belongsTo(PostRoom::class);
    }

    public function emailBulkRuns(): HasMany
    {
        return $this->hasMany(EmailBulkRun::class);
    }
}
