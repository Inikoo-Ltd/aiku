<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 13:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Actions\Helpers\TicketProject\UI\IndexTicketProjects;
use App\Enums\Helpers\Ticket\TicketProjectStatusEnum;
use App\Models\Helpers\TicketProject;
use App\Models\SysAdmin\User;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketProject
 */
class TicketProjectsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var TicketProject $project */
        $project = $this->resource;
        $progress = IndexTicketProjects::rolledUpProgress($project);
        $health   = IndexTicketProjects::health($project, $project->updates->first()?->health, $progress);

        return [
            'slug'         => $project->slug,
            'name'         => $project->name,
            'status'       => $project->status->value,
            'is_pinned'    => $project->is_pinned,
            'status_label' => TicketProjectStatusEnum::labels()[$project->status->value],
            'health'       => $health['health'],
            'health_label' => $health['health_label'],
            'start_date'   => $project->start_date->toDateString(),
            'target_date'  => $project->target_date?->toDateString(),
            'owner'        => IndexTicketProjects::person($project->owner),
            'members'      => $project->members->map(fn (User $member) => IndexTicketProjects::person($member))->values()->all(),
            'progress'     => $progress,
            'quiet_days'   => IndexTicketProjects::quietDays($project),
            'children'     => $project->relationLoaded('children')
                ? $project->children->map(fn (TicketProject $child) => (new self($child))->toArray($request))->values()->all()
                : [],
        ];
    }
}
