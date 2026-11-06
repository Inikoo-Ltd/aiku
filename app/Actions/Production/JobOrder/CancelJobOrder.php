<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Oct 2026 16:30:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Actions\OrgAction;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\JobOrder;
use App\Actions\Production\JobOrderItemTask\ShareMixPool;
use App\Models\Production\JobOrderItem;
use Illuminate\Support\Arr;
use App\Models\Production\JobOrderItemTask;
use Illuminate\Support\Collection;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\ManufactureTaskSessionShare;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class CancelJobOrder extends OrgAction
{
    public const array MODES = ['backlog', 'cancel'];

    public const array KINDS = ['missing_label', 'missing_packaging', 'missing_materials', 'other'];

    private ?User $user = null;

    public static function kindLabels(): array
    {
        return [
            'missing_label'     => __('Missing label'),
            'missing_packaging' => __('Missing packaging'),
            'missing_materials' => __('Missing materials'),
            'other'             => __('Other'),
        ];
    }

    /**
     * Takes a job that cannot be made off the artisan and the floor. Finished units stay on the
     * job to be received; the rest of its to-produce lines go back to To Produce. A job with
     * nothing finished closes as not received. Back to backlog and cancel do the same to the
     * job, they only record a different intent.
     */
    public function handle(JobOrder $jobOrder, array $modelData): JobOrder
    {
        DB::transaction(function () use ($jobOrder, $modelData) {
            $lockedJobOrder = JobOrder::lockForUpdate()->findOrFail($jobOrder->id);

            if (!in_array($lockedJobOrder->state, JobOrderStateEnum::open()) || isset($lockedJobOrder->data['cancellation'])) {
                throw ValidationException::withMessages([
                    'mode' => __('Only an open job order can be cancelled or sent back to the backlog'),
                ]);
            }

            $items   = $lockedJobOrder->jobOrderItems()->with(['artefact.manufactureTasks', 'artefact.orgStock', 'tasks'])->get();
            $taskIds = $items->flatMap(fn (JobOrderItem $item) => $item->tasks->pluck('id'));

            $isBeingWorked = ManufactureTaskSession::whereIn('job_order_item_task_id', $taskIds)->where('state', ManufactureTaskSessionStateEnum::OPEN)->exists()
                || ManufactureTaskSessionShare::whereIn('job_order_item_task_id', $taskIds)
                    ->whereHas('session', fn ($query) => $query->where('state', ManufactureTaskSessionStateEnum::OPEN))
                    ->exists();
            if ($isBeingWorked) {
                throw ValidationException::withMessages([
                    'mode' => __('Someone is working on this job right now, try again when they stop'),
                ]);
            }

            $madeByItem = $items->mapWithKeys(fn (JobOrderItem $item) => [
                $item->id => (int) min($item->quantity, $item->finalTask()?->artefactsIn() ?? 0),
            ])->all();
            $totalMade    = array_sum($madeByItem);
            $totalPlanned = (int) $items->sum('quantity');

            $coveredUnitsByLine = [];
            $packedInByLine     = [];
            if ($totalMade > 0) {
                foreach (GetJobOrderDestinationAllocation::run($lockedJobOrder, $madeByItem) as $allocation) {
                    if ($allocation['line']) {
                        $lineId                      = $allocation['line']->id;
                        $coveredUnitsByLine[$lineId] = ($coveredUnitsByLine[$lineId] ?? 0) + $allocation['quantity'];
                        $packedInByLine[$lineId]     = $allocation['item']->artefact->packedIn();
                    }
                }
            }

            $lines = PartnerShoppingListItem::where('job_order_id', $lockedJobOrder->id)->where('state', ShoppingListItemStateEnum::OPEN)->get();
            foreach ($lines as $line) {
                $coveredSkos = isset($packedInByLine[$line->id]) ? round($coveredUnitsByLine[$line->id] / $packedInByLine[$line->id], 3) : 0;
                $fillItem    = $items->first(fn (JobOrderItem $item) => Arr::get($item->data, 'fill.to_produce_id') == $line->id);
                if ($fillItem) {
                    $coveredSkos = (float) ($madeByItem[$fillItem->id] ?? 0);
                }
                $line->moveUncoveredTo($coveredSkos, null);
            }

            if ($totalMade > 0) {
                foreach ($items as $item) {
                    $made = $madeByItem[$item->id];
                    $item->update(['quantity' => $made]);
                    foreach ($item->tasks as $task) {
                        $task->setRequiredQuantity(min((float) $task->quantity_required, $task->requiredFor($made)));
                    }
                    FillToOrder::make()->release($item);
                }
            } else {
                self::leaveCombinedBatches($taskIds->all());
            }

            $data                 = $lockedJobOrder->data;
            $data['cancellation'] = [
                'mode'            => $modelData['mode'],
                'kind'            => $modelData['kind'],
                'reason'          => $modelData['reason'] ?? null,
                'by'              => $this->user?->contact_name,
                'user_id'         => $this->user?->id,
                'at'              => now()->toDateTimeString(),
                'units_kept'      => $totalMade,
                'units_returned'  => $totalPlanned - $totalMade,
                'lines_returned'  => $lines->count(),
            ];

            $lockedJobOrder->update(array_merge(
                ['data' => $data],
                $totalMade > 0 ? [] : ['state' => JobOrderStateEnum::NOT_RECEIVED, 'not_received_at' => now()]
            ));

            if ($totalMade == 0) {
                ShareMixPool::make()->giveBackAll(self::sharedSteps($items));
            }
        });

        return $jobOrder->refresh();
    }

    /**
     * The shared steps (a mix, or the Making of a labelled strand) of these lines: each hands back what its
     * later steps have not used (ShareMixPool::giveBackAll, HELP-4562).
     *
     * @param  iterable<JobOrderItem>  $items  with tasks
     * @return Collection<int, JobOrderItemTask>
     */
    public static function sharedSteps(iterable $items): Collection
    {
        return collect($items)
            ->flatMap(fn (JobOrderItem $item) => $item->tasks->filter(fn (JobOrderItemTask $task) => ShareMixPool::poolKey($task)))
            ->values();
    }

    /**
     * A combined mix lives on its lead step; when the lead leaves, the next step leads.
     *
     * @param  array<int, int>  $taskIds
     */
    public static function leaveCombinedBatches(array $taskIds): void
    {
        $leadIds = JobOrderItemTask::whereIn('id', $taskIds)->whereNotNull('combined_task_id')->distinct()->pluck('combined_task_id');
        JobOrderItemTask::whereIn('id', $taskIds)->update(['combined_task_id' => null]);

        foreach ($leadIds as $leadId) {
            $remaining = JobOrderItemTask::where('combined_task_id', $leadId)->orderBy('id')->pluck('id');
            JobOrderItemTask::whereIn('id', $remaining)->update(['combined_task_id' => $remaining->count() > 1 ? $remaining->first() : null]);
        }
    }

    public function rules(): array
    {
        return [
            'mode'   => ['required', Rule::in(self::MODES)],
            'kind'   => ['required', Rule::in(self::KINDS)],
            'reason' => ['required_if:kind,other', 'nullable', 'string', 'max:255'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function action(JobOrder $jobOrder, array $modelData, ?User $user = null): JobOrder
    {
        $this->asAction = true;
        $this->user     = $user;
        $this->initialisationFromProduction($jobOrder->production, $modelData);

        return $this->handle($jobOrder, $this->validatedData);
    }

    public function asController(JobOrder $jobOrder, ActionRequest $request): JobOrder
    {
        $this->user = $request->user();
        $this->initialisationFromProduction($jobOrder->production, $request);

        return $this->handle($jobOrder, $this->validatedData);
    }

    public function htmlResponse(JobOrder $jobOrder): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status' => 'success',
            'title'  => $jobOrder->data['cancellation']['mode'] === 'backlog'
                ? __(':reference sent back to the backlog', ['reference' => $jobOrder->reference])
                : __(':reference cancelled', ['reference' => $jobOrder->reference]),
        ]);
    }
}
