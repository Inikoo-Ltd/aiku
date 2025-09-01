<?php

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $task_id
 * @property int $number_users
 * @property string $task_materials_cost
 * @property string $task_energy_cost
 * @property string $task_other_cost
 * @property string $task_work_cost
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\SysAdmin\Task $task
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskStat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskStat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskStat onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskStat query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskStat withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskStat withoutTrashed()
 * @mixin \Eloquent
 */
class TaskStat extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function task(): BelongsTo
    {

        return $this->belongsTo(Task::class);

    }

}
