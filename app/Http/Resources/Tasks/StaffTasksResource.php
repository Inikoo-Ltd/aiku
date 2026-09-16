<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Tasks;

use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\Tasks\StaffTask;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaffTask
 */
class StaffTasksResource extends JsonResource
{
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
            'assignee_name'      => $this->assignee?->chatName(),
            'department_label'   => $this->department ? StaffTask::departmentLabel($this->department) : null,
            'due_at'             => $this->due_at,
            'created_at'         => $this->created_at,
            'closed_at'          => $this->closed_at,
            'conversation_ulid'  => $this->conversation?->ulid,
        ];
    }
}
