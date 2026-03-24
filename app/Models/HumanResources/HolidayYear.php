<?php

namespace App\Models\HumanResources;

use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $group_id
 * @property int|null $organisation_id
 * @property string $label
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Group|null $group
 * @property-read Organisation|null $organisation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HolidayYear newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HolidayYear newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HolidayYear query()
 * @mixin \Eloquent
 */
class HolidayYear extends Model
{
    protected $fillable = [
        'group_id',
        'organisation_id',
        'label',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_active'  => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
