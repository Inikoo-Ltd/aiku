<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItem;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrder\CancelJobOrder;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Events\BroadcastManufactureFloorChanged;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\JobOrderItem;
use App\Models\Production\JobOrderItemTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Removes a job order line (the item and its splits) nobody has worked on yet, e.g. one added
 * by mistake (HELP-4500). Its To Produce lines go back to To Produce unless another line on the
 * job makes the same stock.
 */
class DeleteJobOrderItem extends OrgAction
{
    private JobOrderItem $line;

    public function handle(JobOrderItem $line): void
    {
        $jobOrder = $line->jobOrder;
        $subJobs  = $line->withSplits();
        $stockId  = $line->artefact->orgStock?->stock_id;

        DB::transaction(function () use ($jobOrder, $subJobs, $stockId) {
            $taskIds = JobOrderItemTask::whereIn('job_order_item_id', $subJobs->pluck('id'))->pluck('id');
            CancelJobOrder::leaveCombinedBatches($taskIds->all());

            foreach ($subJobs as $subJob) {
                $subJob->tasks()->delete();
                $subJob->delete();
            }

            $stockStillMade = $jobOrder->jobOrderItems()->whereHas('artefact.orgStock', fn ($query) => $query->where('stock_id', $stockId))->exists();
            if ($stockId && !$stockStillMade) {
                PartnerShoppingListItem::where('job_order_id', $jobOrder->id)
                    ->where('stock_id', $stockId)
                    ->where('state', ShoppingListItemStateEnum::OPEN)
                    ->get()
                    ->each(fn (PartnerShoppingListItem $shoppingListItem) => $shoppingListItem->moveUncoveredTo(0, null));
            }
        });

        BroadcastManufactureFloorChanged::dispatch($jobOrder->production_id);
    }

    /**
     * @param  Collection<int, JobOrderItem>  $subJobs  the line's item and its splits, with tasks.sessions and tasks.sessionShares
     */
    public static function refusal(JobOrderItem $line, Collection $subJobs): ?string
    {
        if (!in_array($line->jobOrder->state, JobOrderStateEnum::open())) {
            return __('Only a line on an open job order can be removed.');
        }

        $isWorked = $subJobs->contains(fn (JobOrderItem $subJob) => (float) $subJob->quantity_received > 0
            || $subJob->tasks->contains(fn (JobOrderItemTask $task) => $task->state !== JobOrderItemTaskStateEnum::TODO
                || (float) $task->quantity_made > 0
                || $task->sessions->isNotEmpty()
                || $task->sessionShares->isNotEmpty()));
        if ($isWorked) {
            return __('Work is already recorded on this line, it can no longer be removed.');
        }

        if (JobOrderItem::carryOver($subJobs)) {
            return __('This line is part of a carried job, change it on the job it was carried from.');
        }

        if ($line->jobOrder->jobOrderItems()->whereNotIn('id', $subJobs->pluck('id'))->doesntExist()) {
            return __('This is the only line on the job order, cancel the job order instead.');
        }

        return null;
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
        $this->initialisation($jobOrderItem->organisation, $request);

        $this->handle($this->line);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
