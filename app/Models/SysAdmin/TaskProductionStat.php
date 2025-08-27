<?php

namespace App\Models\SysAdmin;

use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Models\Production\Production;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $task_id
 * @property int $production_id
 * @property int $number_users
 * @property string $task_materials_cost
 * @property string $task_energy_cost
 * @property string $task_other_cost
 * @property string $task_work_cost
 * @property ManufactureTaskOperativeRewardTermsEnum $operative_reward_terms
 * @property ManufactureTaskOperativeRewardAllowanceTypeEnum $operative_reward_allowance_type
 * @property float $operative_reward_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Production $production
 * @property-read \App\Models\SysAdmin\Task $task
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProductionStat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProductionStat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProductionStat onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProductionStat query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProductionStat withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskProductionStat withoutTrashed()
 * @mixin \Eloquent
 */
class TaskProductionStat extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts   = [
        'operative_reward_terms'              => ManufactureTaskOperativeRewardTermsEnum::class,
        'operative_reward_allowance_type'     => ManufactureTaskOperativeRewardAllowanceTypeEnum::class,
    ];

    public function task(): BelongsTo
    {

        return $this->belongsTo(Task::class);

    }
    public function production(): BelongsTo
    {

        return $this->belongsTo(Production::class);

    }
}
