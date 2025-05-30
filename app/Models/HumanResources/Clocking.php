<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Jun 2023 03:43:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\HumanResources;

use App\Enums\HumanResources\Clocking\ClockingTypeEnum;
use App\Models\Helpers\Media;
use App\Models\Traits\HasImage;
use App\Models\Traits\InOrganisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $workplace_id
 * @property int|null $timesheet_id
 * @property ClockingTypeEnum $type
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int|null $time_tracker_id
 * @property int|null $clocking_machine_id
 * @property \Illuminate\Support\Carbon $clocked_at
 * @property string|null $generator_type
 * @property int|null $generator_id
 * @property string|null $notes
 * @property int|null $image_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $deleted_by_type
 * @property int|null $deleted_by_id
 * @property string|null $source_id
 * @property-read \App\Models\HumanResources\ClockingMachine|null $clockingMachine
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $images
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read Media|null $seoImage
 * @property-read Model|\Eloquent|null $subject
 * @property-read \App\Models\HumanResources\Timesheet|null $timesheet
 * @property-read \App\Models\HumanResources\Workplace|null $workplace
 * @method static Builder<static>|Clocking newModelQuery()
 * @method static Builder<static>|Clocking newQuery()
 * @method static Builder<static>|Clocking onlyTrashed()
 * @method static Builder<static>|Clocking query()
 * @method static Builder<static>|Clocking withTrashed()
 * @method static Builder<static>|Clocking withoutTrashed()
 * @mixin Eloquent
 */
class Clocking extends Model implements HasMedia
{
    use SoftDeletes;
    use InOrganisation;
    use HasImage;

    protected $casts = [
        'clocked_at'      => 'datetime:Y-m-d H:i:s',
        'type'            => ClockingTypeEnum::class,
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];


    protected $guarded = [];

    public function subject(): BelongsTo
    {
        return $this->morphTo();
    }

    public function workplace(): BelongsTo
    {
        return $this->belongsTo(Workplace::class);
    }

    public function clockingMachine(): BelongsTo
    {
        return $this->belongsTo(ClockingMachine::class);
    }

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }


}
