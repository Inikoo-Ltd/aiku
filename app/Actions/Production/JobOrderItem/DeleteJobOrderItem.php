<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItem;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrder\CancelJobOrder;
use App\Actions\Production\JobOrder\GetJobOrderDestinationAllocation;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Events\BroadcastManufactureFloorChanged;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItem;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\ManufactureTaskSessionShare;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Sends one job order line back to the backlog (HELP-4552), e.g. one that cannot be made for
 * missing materials or was added by mistake (HELP-4500). A line nobody has worked on leaves the
 * job and the artisan's tablet; a line with work recorded keeps what is made and only the rest
 * goes. The To Produce lines it was making go back to To Produce, less what the units it keeps
 * and the job's other lines still cover. Who, when and why is kept on the job. A worked line
 * split between artisans is refused: change the split first.
 */
class DeleteJobOrderItem extends OrgAction
{
    private JobOrderItem $line;

    private ?User $user = null;

    public static function kindLabels(): array
    {
        return Arr::except(CancelJobOrder::kindLabels(), 'other') + [
            'added_by_mistake' => __('Added by mistake'),
            'other'            => __('Other'),
        ];
    }

    public function handle(JobOrderItem $line, array $modelData = []): void
    {
        $jobOrder = $line->jobOrder;
        $subJobs  = $line->withSplits()->load(['tasks.sessions', 'tasks.sessionShares', 'artefact.manufactureTasks', 'artefact.orgStock']);
        $stockId  = $line->artefact->orgStock?->stock_id;
        $planned  = (int) $subJobs->sum('quantity');
        $made     = self::madeOn($subJobs);

        DB::transaction(function () use ($jobOrder, $line, $subJobs, $stockId, $planned, $made, $modelData) {
            $lockedJobOrder = JobOrder::lockForUpdate()->findOrFail($jobOrder->id);
            $line->setRelation('jobOrder', $lockedJobOrder);
            if ($refusal = self::refusal($line, $line->withSplits()->load(['tasks.sessions', 'tasks.sessionShares']))) {
                throw ValidationException::withMessages(['line' => $refusal]);
            }

            $madeByItem = $lockedJobOrder->jobOrderItems()->pluck('quantity', 'id')->map(fn ($quantity) => (float) $quantity)->all();
            foreach ($subJobs as $subJob) {
                $madeByItem[$subJob->id] = $made;
            }

            $coveredSkosByDemandLine = [];
            foreach (GetJobOrderDestinationAllocation::run($lockedJobOrder, $madeByItem) as $allocation) {
                if ($allocation['line']) {
                    $coveredSkosByDemandLine[$allocation['line']->id] = ($coveredSkosByDemandLine[$allocation['line']->id] ?? 0)
                        + $allocation['quantity'] / $allocation['item']->artefact->packedIn();
                }
            }

            if (self::isWorked($subJobs)) {
                UpdateJobOrderItem::run($line, ['quantity' => $made]);
            } else {
                $taskIds = JobOrderItemTask::whereIn('job_order_item_id', $subJobs->pluck('id'))->pluck('id');
                CancelJobOrder::leaveCombinedBatches($taskIds->all());

                foreach ($subJobs as $subJob) {
                    $subJob->tasks()->delete();
                    $subJob->delete();
                }
            }

            $demandLinesReturned = 0;
            if ($stockId) {
                PartnerShoppingListItem::where('job_order_id', $lockedJobOrder->id)
                    ->where('stock_id', $stockId)
                    ->where('state', ShoppingListItemStateEnum::OPEN)
                    ->where(fn ($query) => $query->whereNull('transaction_id')
                        ->orWhereHas('transaction', fn ($query) => $query->whereNotIn('state', [TransactionStateEnum::DISPATCHED, TransactionStateEnum::CANCELLED])))
                    ->get()
                    ->each(function (PartnerShoppingListItem $demandLine) use ($coveredSkosByDemandLine, &$demandLinesReturned) {
                        $coveredSkos = round($coveredSkosByDemandLine[$demandLine->id] ?? 0, 3);
                        if ($coveredSkos < $demandLine->claimedSkos()) {
                            $demandLinesReturned++;
                        }
                        $demandLine->moveUncoveredTo($coveredSkos, null);
                    });
            }

            $lockedJobOrder->refresh();
            $data                   = $lockedJobOrder->data ?? [];
            $data['line_returns'][] = [
                'artefact_code'  => $line->artefact->code,
                'kind'           => $modelData['kind'] ?? 'other',
                'reason'         => $modelData['reason'] ?? null,
                'units_kept'     => $made,
                'units_returned' => $planned - $made,
                'lines_returned' => $demandLinesReturned,
                'by'             => $this->user?->contact_name,
                'user_id'        => $this->user?->id,
                'at'             => now()->toDateTimeString(),
            ];
            $lockedJobOrder->update(['data' => $data]);
        });

        BroadcastManufactureFloorChanged::dispatch($jobOrder->production_id);
    }

    /**
     * @param  Collection<int, JobOrderItem>  $subJobs  the line's item and its splits, with tasks
     */
    public static function madeOn(Collection $subJobs): int
    {
        return (int) $subJobs->sum(fn (JobOrderItem $subJob) => min((int) $subJob->quantity, $subJob->finalTask()?->artefactsIn() ?? 0));
    }

    /**
     * @param  Collection<int, JobOrderItem>  $subJobs  the line's item and its splits, with tasks.sessions and tasks.sessionShares
     */
    public static function isWorked(Collection $subJobs): bool
    {
        return $subJobs->contains(fn (JobOrderItem $subJob) => (float) $subJob->quantity_received > 0
            || $subJob->tasks->contains(fn (JobOrderItemTask $task) => $task->state !== JobOrderItemTaskStateEnum::TODO
                || (float) $task->quantity_made > 0
                || $task->sessions->isNotEmpty()
                || $task->sessionShares->isNotEmpty()));
    }

    /**
     * @param  Collection<int, JobOrderItem>  $subJobs  the line's item and its splits, with tasks.sessions and tasks.sessionShares
     */
    public static function refusal(JobOrderItem $line, Collection $subJobs): ?string
    {
        if (!in_array($line->jobOrder->state, JobOrderStateEnum::open()) || isset($line->jobOrder->data['cancellation'])) {
            return __('Only a line on an open job order can be sent back to the backlog.');
        }

        if (JobOrderItem::carryOver($subJobs)) {
            return __('This line is part of a carried job, change it on the job it was carried from.');
        }

        if ($line->jobOrder->jobOrderItems()->whereNotIn('id', $subJobs->pluck('id'))->doesntExist()) {
            return __('This is the only line on the job order, send the whole job back to the backlog instead.');
        }

        if (!self::isWorked($subJobs)) {
            return null;
        }

        $tasks         = $subJobs->flatMap(fn (JobOrderItem $subJob) => $subJob->tasks);
        $isBeingWorked = $tasks->contains(fn (JobOrderItemTask $task) => $task->sessions->contains('state', ManufactureTaskSessionStateEnum::OPEN))
            || ManufactureTaskSession::whereIn('job_order_item_task_id', $tasks->pluck('combined_task_id')->filter()->unique())
                ->where('is_combined', true)
                ->where('state', ManufactureTaskSessionStateEnum::OPEN)
                ->exists()
            || ManufactureTaskSessionShare::whereIn('job_order_item_task_id', $tasks->pluck('id'))
                ->whereHas('session', fn ($query) => $query->where('state', ManufactureTaskSessionStateEnum::OPEN))
                ->exists();
        if ($isBeingWorked) {
            return __('Someone is working on this line right now, try again when they stop.');
        }

        if ($subJobs->count() > 1) {
            return __('Work is already recorded on this line and it is split between artisans, change the split first.');
        }

        if ((float) $line->quantity_received > 0) {
            return __('This line is already being received, it can no longer go back to the backlog.');
        }

        if (self::madeOn($subJobs) >= (int) $line->quantity) {
            return __('Everything on this line is already made, nothing is left to send back.');
        }

        return null;
    }

    public function rules(): array
    {
        return [
            'kind'   => ['required', Rule::in(array_keys(self::kindLabels()))],
            'reason' => ['required_if:kind,other', 'nullable', 'string', 'max:255'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $subJobs = $this->line->withSplits()->load(['tasks.sessions', 'tasks.sessionShares']);
        if ($refusal = self::refusal($this->line, $subJobs)) {
            $validator->errors()->add('line', $refusal);
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->line->jobOrder->production_id}.orchestrate",
        ]);
    }

    public function asController(JobOrderItem $jobOrderItem, ActionRequest $request): void
    {
        $this->line = $jobOrderItem->split_from_id ? $jobOrderItem->splitFrom : $jobOrderItem;
        $this->user = $request->user();
        $this->initialisation($jobOrderItem->organisation, $request);

        $this->handle($this->line, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
