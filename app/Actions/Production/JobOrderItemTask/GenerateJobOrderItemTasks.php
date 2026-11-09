<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 21:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItemTask;

use App\Actions\Production\ManufactureTask\GetDefaultManufactureTask;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\JobOrderItem;
use App\Models\Production\JobOrderItemTask;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateJobOrderItemTasks
{
    use AsAction;

    /**
     * A step that takes the place of a removed one is not asked for again where the removed step is already
     * finished, so the same work neither holds up the steps after it nor is paid twice.
     *
     * A step new to the recipe is not added where a step after it already has work, since that work was done without it.
     *
     * A step with nothing recorded yet, even one already started, follows a change of the recipe's units per
     * artefact; once work is recorded the step keeps what it asked for, so nothing already counted or paid is restated.
     *
     * A step taken off the recipe and added back takes up the job step it left behind.
     *
     * @param  array<int, int>  $replacedSteps  removed recipe step id by the id of the recipe step replacing it
     * @param  array<int, float|string>  $previousUnitsPerArtefact  units per artefact before the recipe changed, by recipe step id
     * @return array<int, JobOrderItemTask>
     */
    public function handle(JobOrderItem $jobOrderItem, array $replacedSteps = [], array $previousUnitsPerArtefact = []): array
    {
        $jobOrder = $jobOrderItem->jobOrder;
        $tasks    = [];
        $artefact = $jobOrderItem->artefact;

        if ($artefact->recipeSteps()->doesntExist()) {
            $artefact->manufactureTasks()->attach(GetDefaultManufactureTask::run($jobOrder->production)->id, ['position' => 1, 'units_per_artefact' => 1]);
        }

        $jobSteps        = $jobOrderItem->tasks()->get();
        $finishedStepIds = $jobSteps->where('state', JobOrderItemTaskStateEnum::DONE)->pluck('artefact_manufacture_task_id')->filter();
        $workedStepIds   = $jobOrderItem->tasks()->whereNot(fn ($query) => $query->unworked())->pluck('artefact_manufacture_task_id')->filter();
        $recipeSteps     = $artefact->recipeSteps()->get();

        foreach ($recipeSteps as $recipeStep) {
            $task = $jobSteps->firstWhere('artefact_manufacture_task_id', $recipeStep->id)
                ?? $jobSteps->first(fn (JobOrderItemTask $jobStep) => $jobStep->artefact_manufacture_task_id === null
                    && ArtefactManufactureTask::key($jobStep->manufacture_task_id, $jobStep->label) === $recipeStep->stepKey());

            if (!$task) {
                $laterStepIsWorked = $recipeSteps
                    ->filter(fn (ArtefactManufactureTask $step) => $step->position > $recipeStep->position)
                    ->pluck('id')
                    ->intersect($workedStepIds)
                    ->isNotEmpty();

                if ($laterStepIsWorked || $finishedStepIds->contains($replacedSteps[$recipeStep->id] ?? null)) {
                    continue;
                }

                $task = JobOrderItemTask::create([
                    'job_order_item_id'            => $jobOrderItem->id,
                    'artefact_manufacture_task_id' => $recipeStep->id,
                    'manufacture_task_id'          => $recipeStep->manufacture_task_id,
                    'label'                        => $recipeStep->label,
                    'group_id'                     => $jobOrderItem->group_id,
                    'organisation_id'              => $jobOrderItem->organisation_id,
                    'production_id'                => $jobOrder->production_id,
                    'job_order_id'                 => $jobOrder->id,
                    'state'                        => JobOrderItemTaskStateEnum::TODO,
                    'position'                     => $recipeStep->position,
                    'quantity_required'            => ArtefactManufactureTask::stepQuantity($jobOrderItem->quantity, $recipeStep->units_per_artefact),
                ]);
            } elseif ($task->artefact_manufacture_task_id === null) {
                $task->update(['artefact_manufacture_task_id' => $recipeStep->id, 'label' => $recipeStep->label]);
                $jobSteps = $jobSteps->reject(fn (JobOrderItemTask $jobStep) => $jobStep->id == $task->id);
            }

            if ($task->state == JobOrderItemTaskStateEnum::TODO && $task->position != $recipeStep->position) {
                $task->update(['position' => $recipeStep->position]);
            }

            $previousUnits = ArtefactManufactureTask::exactUnits($previousUnitsPerArtefact[$recipeStep->id] ?? 0);
            $units         = ArtefactManufactureTask::exactUnits($recipeStep->units_per_artefact);
            if (!$task->wasRecentlyCreated && $previousUnits > 0 && abs($previousUnits - $units) > 1e-9
                && $task->state != JobOrderItemTaskStateEnum::DONE && (float) $task->quantity_made == 0) {
                $task->setRequiredQuantity(round((float) $task->quantity_required * $units / $previousUnits, 3));
            }

            $tasks[] = $task;
        }

        foreach ($tasks as $task) {
            if ($task->wasRecentlyCreated) {
                ShareMixPool::make()->settle($task);
            }
        }

        if (in_array($jobOrder->state, JobOrderStateEnum::open())) {
            $jobOrderItem->tasks()
                ->where(fn ($query) => $query->whereNull('artefact_manufacture_task_id')->orWhereNotIn('artefact_manufacture_task_id', $artefact->recipeSteps()->select('id')))
                ->unworked()
                ->delete();
            $jobOrderItem->unsetRelation('tasks');
        }

        return $tasks;
    }

    /**
     * What a recipe change is about to do to the open job orders of an artefact, so whoever saves it is told and confirms first.
     *
     * @param  array<string, float|string>  $newUnitsPerArtefact  units per artefact the recipe is changing to, by step key (ArtefactManufactureTask::key); steps left out are unchanged
     * @param  array<int, string>  $removedStepKeys  steps the change takes out of the recipe
     * @return array{open: Collection<int, string>, keeping: Collection<int, string>}|null null when no open job order is affected
     */
    public function openJobsAffectedBy(Artefact $artefact, array $newUnitsPerArtefact, array $removedStepKeys = []): ?array
    {
        $currentSteps   = $artefact->recipeSteps()->get()->keyBy(fn (ArtefactManufactureTask $step) => $step->stepKey());
        $changedKeys    = collect($newUnitsPerArtefact)
            ->filter(fn ($units, string $key) => !$currentSteps->has($key)
                || abs(ArtefactManufactureTask::exactUnits($currentSteps[$key]->units_per_artefact) - ArtefactManufactureTask::exactUnits($units)) > 1e-9)
            ->keys()
            ->merge($removedStepKeys);

        if ($changedKeys->isEmpty()) {
            return null;
        }

        $openItems = JobOrderItem::where('artefact_id', $artefact->id)
            ->whereHas('jobOrder', fn ($query) => $query->whereNotIn('state', [JobOrderStateEnum::RECEIVED, JobOrderStateEnum::NOT_RECEIVED]))
            ->with('jobOrder')
            ->get();

        if ($openItems->isEmpty()) {
            return null;
        }

        $label = fn (JobOrderItem $jobOrderItem) => $jobOrderItem->jobOrder->reference.' '.$artefact->code;

        return [
            'open'    => $openItems->map($label)->unique()->values(),
            'keeping' => JobOrderItemTask::whereIn('job_order_item_id', $openItems->pluck('id'))
                ->whereIn('artefact_manufacture_task_id', $currentSteps->toBase()->only($changedKeys->all())->pluck('id'))
                ->where('quantity_made', '>', 0)
                ->get()
                ->map(fn (JobOrderItemTask $task) => $label($openItems->firstWhere('id', $task->job_order_item_id)))
                ->unique()
                ->values(),
        ];
    }

    /**
     * @param  array<int, array{open: Collection<int, string>, keeping: Collection<int, string>}|null>  $affected
     */
    public static function openJobsConfirmationMessage(array $affected): ?string
    {
        $affected = array_filter($affected);
        if (!$affected) {
            return null;
        }

        $open    = collect($affected)->flatMap(fn (array $jobs) => $jobs['open'])->unique();
        $keeping = collect($affected)->flatMap(fn (array $jobs) => $jobs['keeping'])->unique();

        $message = __('Open job orders that change with this recipe: :jobs.', ['jobs' => $open->implode(', ')]);
        if ($keeping->isNotEmpty()) {
            $message .= ' '.__('Work is already recorded on :jobs, so those steps keep their old count.', ['jobs' => $keeping->implode(', ')]);
        }

        return $message;
    }

    /**
     * @param  array<int, float|string>  $previousUnitsPerArtefact  units per artefact before the recipe changed, by recipe step id
     * @return Collection<int, string> open job orders with work recorded on a step whose units changed, which keep the count they had
     */
    public function jobsKeepingTheirCount(Artefact $artefact, array $previousUnitsPerArtefact): Collection
    {
        $changedStepIds = $artefact->recipeSteps()->get()
            ->filter(fn (ArtefactManufactureTask $step) => isset($previousUnitsPerArtefact[$step->id])
                && abs(ArtefactManufactureTask::exactUnits($previousUnitsPerArtefact[$step->id]) - ArtefactManufactureTask::exactUnits($step->units_per_artefact)) > 1e-9)
            ->pluck('id');

        return JobOrderItemTask::whereIn('artefact_manufacture_task_id', $changedStepIds)
            ->whereHas('jobOrderItem', fn ($query) => $query->where('artefact_id', $artefact->id))
            ->whereHas('jobOrder', fn ($query) => $query->whereNotIn('state', [JobOrderStateEnum::RECEIVED, JobOrderStateEnum::NOT_RECEIVED]))
            ->where('quantity_made', '>', 0)
            ->with('jobOrder')
            ->get()
            ->map(fn (JobOrderItemTask $task) => $task->jobOrder->reference.' '.$artefact->code)
            ->unique()
            ->values();
    }
}
