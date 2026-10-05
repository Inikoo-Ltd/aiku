<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Models\Tasks\StaffTask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $ticket_project_id
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property \Illuminate\Support\Carbon|null $done_at
 * @property int $position
 * @property-read TicketProject $project
 * @mixin \Eloquent
 */
class TicketProjectMilestone extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date'   => 'date',
            'done_at'    => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(TicketProject::class, 'ticket_project_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function staffTasks(): HasMany
    {
        return $this->hasMany(StaffTask::class);
    }
}
