<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 21:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $production_id
 * @property int $job_order_item_task_id
 * @property int $manufacture_task_id
 * @property int $user_id
 * @property int|null $employee_id
 * @property ManufactureTaskSessionStateEnum $state
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property numeric $quantity_made
 * @property numeric $quantity_rejected
 * @property numeric|null $task_work_cost
 * @property string|null $operative_reward_terms
 * @property string|null $operative_reward_allowance_type
 * @property numeric|null $operative_reward_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Employee|null $employee
 * @property-read Group|null $group
 * @property-read \App\Models\Production\JobOrderItemTask|null $jobOrderItemTask
 * @property-read \App\Models\Production\ManufactureTask|null $manufactureTask
 * @property-read Organisation $organisation
 * @property-read \App\Models\Production\Production|null $production
 * @property-read User $user
 * @mixin \Eloquent
 */
class ManufactureTaskSession extends Model
{
    protected $guarded = [];

    protected $casts = [
        'state'      => ManufactureTaskSessionStateEnum::class,
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function jobOrderItemTask(): BelongsTo
    {
        return $this->belongsTo(JobOrderItemTask::class);
    }

    public function manufactureTask(): BelongsTo
    {
        return $this->belongsTo(ManufactureTask::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
