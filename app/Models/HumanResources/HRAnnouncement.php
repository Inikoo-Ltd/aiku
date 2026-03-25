<?php

namespace App\Models\HumanResources;

use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int|null $employee_id
 * @property string $type
 * @property string $title
 * @property string $message
 * @property array<array-key, mixed>|null $metadata
 * @property bool $is_read
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\HumanResources\Employee|null $employee
 * @property-read Organisation $organisation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HRAnnouncement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HRAnnouncement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HRAnnouncement query()
 * @mixin \Eloquent
 */
class HRAnnouncement extends Model
{
    protected $table = 'hr_announcements';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'is_read' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
