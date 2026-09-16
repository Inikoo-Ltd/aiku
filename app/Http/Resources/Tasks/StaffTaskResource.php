<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Tasks;

use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Models\Tasks\StaffTask;
use App\Models\SysAdmin\User;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaffTask
 */
class StaffTaskResource extends JsonResource
{
    private function person(?User $user): ?array
    {
        return $user ? [
            'id'     => $user->id,
            'name'   => $user->chatName(),
            'avatar' => $user->image_id ? $user->imageSources(0, 48) : null,
        ] : null;
    }

    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'reference'         => $this->reference,
            'subject'           => $this->subject,
            'description'       => $this->description,
            'status'            => $this->status->value,
            'status_label'      => StaffTaskStatusEnum::labels()[$this->status->value],
            'status_icon'       => StaffTaskStatusEnum::stateIcon()[$this->status->value],
            'priority'          => $this->priority->value,
            'priority_icon'     => ChatPriorityEnum::stateIcon()[$this->priority->value] ?? null,
            'department'        => $this->department,
            'department_label'  => $this->department ? StaffTask::departmentLabel($this->department) : null,
            'requester'         => $this->person($this->requester),
            'assignee'          => $this->person($this->assignee),
            'due_at'            => $this->due_at?->toDateString(),
            'is_overdue'        => $this->due_at && $this->status->isOpen() && $this->due_at->isPast(),
            'model_type'        => $this->model_type,
            'model_id'          => $this->model_id,
            'model_label'       => $this->model?->reference ?? $this->model?->code ?? $this->model?->name,
            'conversation_ulid' => $this->conversation?->ulid,
            'closed_at'         => $this->closed_at,
            'created_at'        => $this->created_at,
        ];
    }
}
