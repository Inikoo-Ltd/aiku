<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 16:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The part of a combined session that belongs to one of its job lines: the quantity it gets and
 * the fraction of the session's time and pay that goes with it.
 *
 * @property int $id
 * @property int $manufacture_task_session_id
 * @property int $job_order_item_task_id
 * @property numeric $share
 * @property numeric $quantity_made
 * @property numeric $quantity_rejected
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Production\ManufactureTaskSession $session
 * @property-read \App\Models\Production\JobOrderItemTask $jobOrderItemTask
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManufactureTaskSessionShare newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManufactureTaskSessionShare newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManufactureTaskSessionShare query()
 * @mixin \Eloquent
 */
class ManufactureTaskSessionShare extends Model
{
    protected $guarded = [];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ManufactureTaskSession::class, 'manufacture_task_session_id');
    }

    public function jobOrderItemTask(): BelongsTo
    {
        return $this->belongsTo(JobOrderItemTask::class);
    }
}
