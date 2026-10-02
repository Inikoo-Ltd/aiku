<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Tasks;

use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaffTask
 */
class StaffTasksResource extends JsonResource
{
    private function shortName(?User $user): ?string
    {
        return $user ? strtok($user->chatName(), ' ') : null;
    }

    private function avatar(?User $user): ?array
    {
        return $user?->image_id ? $user->imageSources(48, 48) : null;
    }

    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'reference'          => $this->reference,
            'subject'            => $this->subject,
            'status'             => $this->status->value,
            'status_label'       => StaffTaskStatusEnum::labels()[$this->status->value],
            'status_icon'        => StaffTaskStatusEnum::stateIcon()[$this->status->value],
            'priority'           => $this->priority->value,
            'priority_label'     => ChatPriorityEnum::labels()[$this->priority->value],
            'priority_icon'      => ChatPriorityEnum::stateIcon()[$this->priority->value],
            'requester_name'     => $this->requester?->chatName(),
            'requester_short'    => $this->shortName($this->requester),
            'requester_avatar'   => $this->avatar($this->requester),
            'assignee_id'        => $this->assignee_id,
            'assignee_name'      => $this->assignee?->chatName(),
            'assignee_short'     => $this->shortName($this->assignee),
            'assignee_avatar'    => $this->avatar($this->assignee),
            'collaborators'      => $this->relationLoaded('collaborators') ? $this->collaborators->map(fn (User $user) => ['id' => $user->id, 'name' => $user->chatName()])->values()->all() : [],
            'department_label'   => $this->department ? StaffTask::departmentLabel($this->department) : null,
            'due_at'             => $this->due_at,
            'is_overdue'         => $this->due_at && $this->status->isOpen() && $this->due_at->isPast(),
            'created_at'         => $this->created_at,
            'closed_at'          => $this->closed_at,
            'conversation_ulid'  => $this->conversation?->ulid,
        ];
    }
}
