<?php

namespace App\Models\HumanResources;

use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int|null $restricted_period_id
 * @property int $employee_id
 * @property \Illuminate\Support\Carbon $from_date
 * @property \Illuminate\Support\Carbon $to_date
 * @property int|null $approved_by_id
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $approvedBy
 * @property-read \App\Models\HumanResources\Employee|null $employee
 * @property-read Organisation $organisation
 * @property-read \App\Models\HumanResources\RestrictedPeriod|null $restrictedPeriod
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RestrictedException newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RestrictedException newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RestrictedException query()
 * @mixin \Eloquent
 */
class RestrictedException extends Model
{
    protected $guarded = [];

    protected $casts = [
        'from_date' => 'date',
        'to_date'   => 'date',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function restrictedPeriod(): BelongsTo
    {
        return $this->belongsTo(RestrictedPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
