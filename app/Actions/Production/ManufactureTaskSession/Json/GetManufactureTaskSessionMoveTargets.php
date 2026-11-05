<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession\Json;

use App\Actions\OrgAction;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\JobOrderItem;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GetManufactureTaskSessionMoveTargets extends OrgAction
{
    /**
     * The steps a finished entry can be moved to (HELP-4526): every step of the production's lines on the floor
     * (confirmed) not yet being received, the entry's own job first, then the artisan's other jobs, then everyone else's.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(ManufactureTaskSession $session): array
    {
        $ownJobOrderId = $session->jobOrderItemTask?->job_order_id;

        return JobOrderItem::query()
            ->whereHas('jobOrder', fn ($query) => $query->where('production_id', $session->production_id)->where('state', JobOrderStateEnum::CONFIRMED))
            ->where('quantity_received', '<=', 0)
            ->with(['jobOrder', 'artefact', 'tasks.manufactureTask'])
            ->get()
            ->sortBy(fn (JobOrderItem $line) => [
                $line->job_order_id == $ownJobOrderId ? 0 : (in_array($session->employee_id, [$line->employee_id, $line->jobOrder->employee_id]) && $session->employee_id ? 1 : 2),
                $line->jobOrder->reference,
                $line->id,
            ])
            ->map(fn (JobOrderItem $line) => [
                'label'    => $line->jobOrder->reference.' · '.$line->artefact->code,
                'items'    => $line->tasks
                    ->reject(fn (JobOrderItemTask $step) => $step->id == $session->job_order_item_task_id || $step->combined_task_id)
                    ->map(fn (JobOrderItemTask $step) => [
                        'id'                 => $step->id,
                        'label'              => $line->jobOrder->reference.' · '.$line->artefact->code.' · '.$step->manufactureTask->name,
                        'task_name'          => $step->stepName(),
                        'unit'               => $step->unit(),
                        'pack_size'          => $step->packSize(),
                        'quantity_made'      => (float) $step->quantity_made,
                        'quantity_left'      => max(0, round((float) $step->quantity_required - (float) $step->quantity_made, 3)),
                        'units_per_artefact' => $step->unitsPerArtefact(),
                        'line_quantity'      => (int) $line->quantity,
                        'can_grow'           => !Arr::has($line->data ?? [], 'carried_to'),
                    ])->values()->all(),
            ])
            ->filter(fn (array $group) => $group['items'])
            ->values()
            ->all();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
            "accounting.{$this->organisation->id}.edit",
        ]);
    }

    public function asController(ManufactureTaskSession $manufactureTaskSession, ActionRequest $request): array
    {
        $this->initialisationFromProduction($manufactureTaskSession->production, $request);

        return $this->handle($manufactureTaskSession);
    }
}
