<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Oct 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItem;

use App\Actions\OrgAction;
use App\Events\BroadcastManufactureFloorChanged;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Models\Production\JobOrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class SplitJobOrderItem extends OrgAction
{
    private JobOrderItem $jobOrderItem;

    /**
     * Shares one line of a job order between artisans. The line keeps its first sub-job, each extra
     * artisan gets a sibling item of the same artefact and batch, so the floor, the pay and the
     * receiving keep working item by item. Sub-jobs dropped from the list must not have been started.
     *
     * @param  array<int, array{id?: int|null, employee_id: int, quantity: int}>  $assignments
     */
    public function handle(JobOrderItem $jobOrderItem, array $assignments): JobOrderItem
    {
        $line = $this->line($jobOrderItem);

        DB::transaction(function () use ($line, $assignments) {
            $subJobs     = $this->subJobs($line)->keyBy('id');
            $splitData   = Arr::except($line->data, ['demand_skos', 'carried_to']);
            $keptIds     = [];

            foreach ($assignments as $assignment) {
                $subJob = $subJobs->get($assignment['id'] ?? 0);

                if (!$subJob) {
                    $subJob = StoreJobOrderItem::make()->action($line->jobOrder, [
                        'artefact_id' => $line->artefact_id,
                        'quantity'    => $assignment['quantity'],
                        'data'        => $splitData,
                    ]);
                } elseif ($subJob->quantity != $assignment['quantity']) {
                    UpdateJobOrderItem::make()->action($subJob, ['quantity' => $assignment['quantity']]);
                }

                if ($subJob->employee_id != $assignment['employee_id']) {
                    $subJob->tasks()->where('state', '!=', JobOrderItemTaskStateEnum::DONE)->update(['employee_id' => null]);
                }

                $subJob->update([
                    'employee_id'   => $assignment['employee_id'],
                    'split_from_id' => $subJob->id == $line->id ? null : $line->id,
                    'data'          => [...$splitData, ...Arr::only($subJob->data ?? [], ['carried_to'])],
                ]);

                $keptIds[] = $subJob->id;
            }

            foreach ($subJobs->except($keptIds) as $droppedSubJob) {
                $droppedSubJob->tasks()->delete();
                $droppedSubJob->delete();
            }

            foreach (JobOrderItem::whereIn('id', $keptIds)->with('tasks')->orderBy('id')->get() as $subJob) {
                CreditOrderWorkToJobOrderItem::run($subJob);
            }
        });

        BroadcastManufactureFloorChanged::dispatch($line->jobOrder->production_id);

        return $line;
    }

    private function line(JobOrderItem $jobOrderItem): JobOrderItem
    {
        return $jobOrderItem->split_from_id ? $jobOrderItem->splitFrom : $jobOrderItem;
    }

    /**
     * @return Collection<int, JobOrderItem>
     */
    private function subJobs(JobOrderItem $line): Collection
    {
        return JobOrderItem::where('id', $line->id)
            ->orWhere('split_from_id', $line->id)
            ->with(['tasks.sessions', 'tasks.manufactureTask', 'artefact.manufactureTasks'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->jobOrderItem->jobOrder->production_id}.orchestrate",
        ]);
    }

    public function rules(): array
    {
        return [
            'assignments'               => ['required', 'array', 'min:1'],
            'assignments.*.id'          => ['sometimes', 'nullable', 'integer'],
            'assignments.*.employee_id' => ['required', 'integer', 'distinct', Rule::exists('employees', 'id')->where('organisation_id', $this->organisation->id)],
            'assignments.*.quantity'    => ['required', 'integer', 'min:1'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $line    = $this->line($this->jobOrderItem);
        $subJobs = $this->subJobs($line)->keyBy('id');

        if (!in_array($line->jobOrder->state, JobOrderStateEnum::open()) || $subJobs->sum('quantity_received') > 0) {
            $validator->errors()->add('assignments', __('This job is already being received, it can no longer be split.'));

            return;
        }

        $assignments = collect($this->get('assignments'));
        $total       = (int) $subJobs->sum('quantity');

        if ($assignments->sum('quantity') != $total) {
            $validator->errors()->add('assignments', __('The artisans must share exactly :total units.', ['total' => $total]));

            return;
        }

        $packedIn = $line->artefact->packedIn();
        if ($total % $packedIn === 0 && $assignments->contains(fn (array $assignment) => $assignment['quantity'] % $packedIn)) {
            $validator->errors()->add('assignments', __('Each artisan gets whole outers: multiples of :count units.', ['count' => $packedIn]));

            return;
        }

        $assignedIds = $assignments->pluck('id')->filter();
        if ($assignedIds->diff($subJobs->keys())->isNotEmpty() || $assignedIds->duplicates()->isNotEmpty()) {
            $validator->errors()->add('assignments', __('This job changed meanwhile, reload the page.'));

            return;
        }

        if (!$assignedIds->contains($line->id)) {
            $validator->errors()->add('assignments', __('The first sub-job cannot be removed.'));

            return;
        }

        foreach ($subJobs->except($assignedIds->all()) as $droppedSubJob) {
            if ($droppedSubJob->tasks->contains(fn ($task) => $task->sessions->isNotEmpty())) {
                $validator->errors()->add('assignments', __(':artisan already started their part, it cannot be removed.', ['artisan' => $droppedSubJob->employee?->contact_name ?? __('Someone')]));

                return;
            }
        }

        foreach ($assignments->whereNotNull('id') as $assignment) {
            $subJob    = $subJobs->get($assignment['id']);
            $carriedTo = $subJob->quantity != $assignment['quantity'] ? $subJob->carriedTo() : null;
            if ($carriedTo) {
                $validator->errors()->add('assignments', __('The rest of this job was carried to :reference, change the quantity there.', ['reference' => $carriedTo->reference]));

                return;
            }

            foreach ($subJob->tasks as $task) {
                if ($task->isRecordedInKg()) {
                    continue;
                }

                $newRequired = $task->requiredFor($assignment['quantity']);
                if ($newRequired < $task->quantity_made) {
                    $validator->errors()->add('assignments', __(':artisan already made :made of :step, more than their new share of :quantity.', [
                        'artisan'  => $subJob->employee?->contact_name ?? __('Someone'),
                        'made'     => (float) $task->quantity_made,
                        'step'     => $task->stepName(),
                        'quantity' => $newRequired,
                    ]));

                    return;
                }
            }
        }
    }

    public function asController(JobOrderItem $jobOrderItem, ActionRequest $request): JobOrderItem
    {
        $this->jobOrderItem = $jobOrderItem;
        $this->initialisationFromProduction($jobOrderItem->jobOrder->production, $request);

        return $this->handle($jobOrderItem, $this->validatedData['assignments']);
    }

    /**
     * @param  array<int, array{id?: int|null, employee_id: int, quantity: int}>  $assignments
     */
    public function action(JobOrderItem $jobOrderItem, array $assignments): JobOrderItem
    {
        $this->jobOrderItem = $jobOrderItem;
        $this->asAction     = true;
        $this->initialisationFromProduction($jobOrderItem->jobOrder->production, ['assignments' => $assignments]);

        return $this->handle($jobOrderItem, $this->validatedData['assignments']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
