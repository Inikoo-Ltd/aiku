<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use App\Models\Tasks\StaffTask;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaffTask
 */
class WarehouseStaffTasksResource extends JsonResource
{
    public function toArray($request): array
    {
        $model = $this->model;

        return [
            'id'            => $this->id,
            'reference'     => $this->reference,
            'subject'       => $this->subject,
            'status'        => $this->status->value,
            'status_label'  => StaffTaskStatusEnum::labels()[$this->status->value],
            'status_icon'   => StaffTaskStatusEnum::stateIcon()[$this->status->value],
            'is_open'       => $this->status->isOpen(),
            'requester'     => $this->requester?->chatName(),
            'assignee'      => $this->assignee?->chatName(),
            'created_at'    => $this->created_at,
            'due_at'        => $this->due_at?->toDateString(),
            'is_overdue'    => $this->due_at && $this->status->isOpen() && $this->due_at->isPast(),
            'days_waiting'  => (int)$this->created_at->diffInDays($this->closed_at ?? now(), true),
            'linked'        => match (true) {
                $model instanceof Location => $model->code,
                $model instanceof OrgStock => $model->code,
                default                    => null,
            },
            'location_slug' => $model instanceof Location ? $model->slug : null,
            'org_stock_slug' => $model instanceof OrgStock ? $model->slug : null,
            'url'           => route('grp.tasks.index', ['task' => $this->reference]),
        ];
    }
}
