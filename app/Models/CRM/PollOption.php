<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 15:00:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use App\Actions\Utils\Abbreviate;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $poll_id
 * @property string $slug
 * @property string $value
 * @property string $label
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\CRM\Poll $poll
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CRM\PollReply> $pollReplies
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read \App\Models\CRM\PollOptionStat|null $stats
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollOption newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollOption newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollOption onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollOption query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollOption withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollOption withoutTrashed()
 * @mixin \Eloquent
 */
class PollOption extends Model implements Auditable
{
    use SoftDeletes;
    use HasHistory;
    use HasSlug;
    use InShop;

    protected $casts = [
        'fetched_at'         => 'datetime',
        'last_fetched_at'    => 'datetime',
    ];

    protected $attributes = [
    ];

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return Abbreviate::run($this->value, 8).'-'.$this->poll->slug;
            })
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128)
            ->doNotGenerateSlugsOnUpdate();
    }

    public function generateTags(): array
    {
        return [
            'crm'
        ];
    }

    protected array $auditInclude = [
        'value',
        'label',
    ];

    public function stats(): HasOne
    {
        return $this->hasOne(PollOptionStat::class);
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function pollReplies(): HasMany
    {
        return $this->hasMany(PollReply::class);
    }

}
