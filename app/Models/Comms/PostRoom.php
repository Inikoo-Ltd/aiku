<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:11:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Enums\Comms\PostRoom\PostRoomCodeEnum;
use App\Models\SysAdmin\Group;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Comms\Comms
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property PostRoomCodeEnum $code
 * @property string $name
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Group $group
 * @property-read \App\Models\Comms\PostRoomIntervals|null $intervals
 * @property-read Collection<int, \App\Models\Comms\OrgPostRoom> $orgPostRooms
 * @property-read Collection<int, \App\Models\Comms\Outbox> $outboxes
 * @property-read \App\Models\Comms\PostRoomStats|null $stats
 *
 * @method static Builder<static>|PostRoom newModelQuery()
 * @method static Builder<static>|PostRoom newQuery()
 * @method static Builder<static>|PostRoom query()
 *
 * @mixin Eloquent
 */
class PostRoom extends Model
{
    use HasSlug;

    protected $casts = [
        'code' => PostRoomCodeEnum::class,
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
                return $this->code->value.'-'.$this->group->slug;
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
        return $this->hasOne(PostRoomStats::class);
    }

    public function intervals(): HasOne
    {
        return $this->hasOne(PostRoomIntervals::class);
    }

    public function outboxes(): HasMany
    {
        return $this->hasMany(Outbox::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function orgPostRooms(): HasMany
    {
        return $this->hasMany(OrgPostRoom::class);
    }
}
