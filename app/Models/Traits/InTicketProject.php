<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use App\Models\Helpers\TicketProject;
use App\Models\Helpers\TicketProjectMilestone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait InTicketProject
{
    public static function bootInTicketProject(): void
    {
        static::saving(function (Model $work) {
            if (!$work->isDirty(['ticket_project_id', 'ticket_project_milestone_id']) || !$work->ticket_project_milestone_id) {
                return;
            }

            $isInProject = $work->ticket_project_id
                && TicketProjectMilestone::whereKey($work->ticket_project_milestone_id)->where('ticket_project_id', $work->ticket_project_id)->exists();

            if (!$isInProject) {
                $work->ticket_project_milestone_id = null;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(TicketProject::class, 'ticket_project_id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(TicketProjectMilestone::class, 'ticket_project_milestone_id');
    }
}
