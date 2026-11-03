<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Production;

use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property numeric $quantity
 * @property int $job_order_id
 * @property string $reference
 * @property string $job_order_slug
 * @property string $job_order_state
 * @property string $planned_for
 * @property int|null $plan_position
 * @property string|null $artefact_code
 * @property string|null $artefact_name
 * @property int|null $artefact_department_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, JobOrderItemTask> $tasks
 */
class JobOrderPlanItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        $isUnfinished = $this->tasks->contains(fn (JobOrderItemTask $task) => $task->state != JobOrderItemTaskStateEnum::DONE);

        return [
            'id'              => $this->id,
            'job_order_id'    => $this->job_order_id,
            'reference'       => $this->reference,
            'job_order_slug'  => $this->job_order_slug,
            'state_label'     => JobOrderStateEnum::labels()[$this->job_order_state] ?? $this->job_order_state,
            'planned_for'     => $this->planned_for,
            'plan_position'   => $this->plan_position,
            'is_carried_over' => $isUnfinished && $this->planned_for < now()->toDateString(),
            'artefact_code'   => $this->artefact_code,
            'artefact_name'   => $this->artefact_name,
            'artefact_department_id' => $this->artefact_department_id,
            'quantity'        => (float) $this->quantity,
            'steps'           => $this->tasks->map(fn (JobOrderItemTask $task) => [
                'id'                => $task->id,
                'name'              => $task->manufactureTask->name,
                'state'             => $task->state,
                'quantity_made'     => (float) $task->quantity_made,
                'quantity_required' => (float) $task->quantity_required,
                'unit'              => $task->unit(),
                'mix_batch_kg'      => $task->mixBatchKg(),
                'employee_id'       => $task->employee_id,
                'only_for'          => $task->manufactureTask->artisans->pluck('id')->all(),
                'operator'          => $task->assignee()?->contact_name,
                'working_on_by'     => $task->sessions->map(fn (ManufactureTaskSession $session) => $session->user->contact_name ?: $session->user->username)->values()->all(),
            ])->values()->all(),
        ];
    }
}
