<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 15:00:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use App\Actions\Utils\Abbreviate;
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property string $slug
 * @property string $name
 * @property string $label
 * @property int $position Position in the poll list
 * @property bool $in_registration
 * @property bool $in_registration_required
 * @property bool $in_iris
 * @property bool $in_iris_required
 * @property PollTypeEnum $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CRM\PollOption> $pollOptions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CRM\PollReply> $pollReplies
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read \App\Models\CRM\PollStat|null $stats
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Poll newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Poll newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Poll onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Poll query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Poll withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Poll withoutTrashed()
 * @mixin \Eloquent
 */
class Poll extends Model implements Auditable
{
    use InShop;
    use SoftDeletes;
    use HasHistory;
    use HasSlug;


    protected $casts = [
        'type'            => PollTypeEnum::class,
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
    ];

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return Abbreviate::run($this->name, 8).'-'.$this->shop->slug;
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
        'name',
        'label',
        'position',
        'in_registration',
        'in_registration_required',
        'in_iris',
        'in_iris_required',
    ];


    public function stats(): HasOne
    {
        return $this->hasOne(PollStat::class);
    }

    public function pollOptions(): HasMany
    {
        return $this->hasMany(PollOption::class);
    }

    public function pollReplies(): HasMany
    {
        return $this->hasMany(PollReply::class);
    }
}
