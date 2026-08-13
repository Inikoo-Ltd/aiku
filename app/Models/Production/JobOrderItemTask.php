<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 21:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $production_id
 * @property int $job_order_id
 * @property int $job_order_item_id
 * @property int $manufacture_task_id
 * @property int $position
 * @property JobOrderItemTaskStateEnum $state
 * @property numeric $quantity_required
 * @property numeric $quantity_made
 * @property numeric $quantity_rejected
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Group|null $group
 * @property-read \App\Models\Production\JobOrder|null $jobOrder
 * @property-read \App\Models\Production\JobOrderItem|null $jobOrderItem
 * @property-read \App\Models\Production\ManufactureTask|null $manufactureTask
 * @property-read Organisation $organisation
 * @property-read \App\Models\Production\Production|null $production
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Production\ManufactureTaskSession> $sessions
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItemTask newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItemTask newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItemTask query()
 * @mixin \Eloquent
 */
class JobOrderItemTask extends Model
{
    protected $guarded = [];

    protected $casts = [
        'state' => JobOrderItemTaskStateEnum::class,
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

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function jobOrderItem(): BelongsTo
    {
        return $this->belongsTo(JobOrderItem::class);
    }

    public function manufactureTask(): BelongsTo
    {
        return $this->belongsTo(ManufactureTask::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ManufactureTaskSession::class);
    }
}
