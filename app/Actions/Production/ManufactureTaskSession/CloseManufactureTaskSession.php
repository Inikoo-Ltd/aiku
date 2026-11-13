<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 21:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\Production\JobOrder\FillToOrder;
use App\Actions\HumanResources\ClockingMachine\ResolvesEmployeeByCode;
use App\Actions\Production\JobOrderItemTask\ShareMixPool;
use App\Actions\Production\RawMaterial\TakeMixRawMaterialsFromStock;
use App\Actions\Production\JobOrderItemTask\UI\ShowManufactureFloor;
use App\Actions\OrgAction;
use App\Actions\Production\JobOrderItemTask\CalculateJobOrderItemTaskQuantities;
use App\Actions\Production\JobOrderItemTask\SettleShortJobOrderItemTask;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Events\BroadcastManufactureFloorChanged;
use App\Enums\Production\Artisan\ArtisanPayTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Actions\Production\ManufactureBreak\EndManufactureBreak;
use App\Models\HumanResources\Employee;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItem;
use App\Models\Production\ManufactureBreak;
use App\Models\Production\ManufacturePreparationReason;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\ManufactureTaskSessionShare;
use App\Models\Production\RecipeStepRawMaterial;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use App\Models\Production\JobOrderItemTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class CloseManufactureTaskSession extends OrgAction
{
    use ResolvesEmployeeByCode;

    public function handle(ManufactureTaskSession $session, array $modelData): ManufactureTaskSession
    {
        return DB::transaction(function () use ($session, $modelData) {
            $session = ManufactureTaskSession::lockForUpdate()->find($session->id);
            if ($session->state == ManufactureTaskSessionStateEnum::CLOSED) {
                throw ValidationException::withMessages([
                    'state' => __('This task session is already closed'),
                ]);
            }

            $openBreak = ManufactureBreak::where('user_id', $session->user_id)->open()->first();
            if ($openBreak) {
                EndManufactureBreak::make()->action($openBreak);
            }

            $finishedBeforeShiftEnd = !empty($modelData['finished_before_shift_end']) && $session->left_open_after_shift_at && $session->shift_end_answer === null;
            $endedAt                = $finishedBeforeShiftEnd ? $session->shiftEndPausedAt() : now();
            if ($finishedBeforeShiftEnd) {
                $session->fill(['shift_end_answer' => 'finished', 'shift_end_resolved_at' => now()]);
            }
            $isQuantityOnly = !$session->isNonProductive() && $session->manufactureTask?->is_quantity_only;
            if ($isQuantityOnly) {
                $session->started_at = $endedAt;
            }
            CalculateManufactureTaskSessionBreakMinutes::run($session, $endedAt);

            if ($session->isNonProductive()) {
                $session->update([
                    'ended_at'         => $endedAt,
                    'state'            => ManufactureTaskSessionStateEnum::CLOSED,
                    'artisan_pay_type' => $session->employee?->artisan_pay_type ?? ArtisanPayTypeEnum::PIECE_RATE,
                ] + $this->preparationReason($session, $modelData));
                if ($session->preparation_reason_note) {
                    TranslateManufacturePreparationReasonNote::dispatch($session->id)->afterCommit();
                }

                return CalculateManufactureTaskSessionPay::run($session);
            }

            $manufactureTask = $session->manufactureTask;

            $task = JobOrderItemTask::lockForUpdate()->find($session->job_order_item_task_id);
            $session->setRelation('jobOrderItemTask', $task);
            $members = $session->is_combined ? $this->combinedMembers($task) : collect([$task]);

            $left         = $members->sum(fn (JobOrderItemTask $member) => max(0, (float) $member->quantity_required - (float) $member->quantity_made));
            $packSize = $members->map(fn (JobOrderItemTask $member) => $member->packSize())->filter()->first();
            if ($packSize && (float) $modelData['quantity_made'] != floor((float) $modelData['quantity_made']) && abs((float) $modelData['quantity_made'] - $left) > 0.001) {
                throw ValidationException::withMessages([
                    'quantity_made' => __('This step is counted in whole packs of :size, enter a whole number', ['size' => $packSize]),
                ]);
            }

            $isRecordedInKg = (bool) $manufactureTask->is_recorded_in_kg;
            $mixBatchKg     = JobOrderItemTask::sharedMixBatchKg($members);
            if ($mixBatchKg && !JobOrderItemTask::isWholeMixBatches((float) $modelData['quantity_made'], $mixBatchKg)) {
                throw ValidationException::withMessages([
                    'quantity_made' => JobOrderItemTask::wholeMixBatchesMessage($mixBatchKg),
                ]);
            }
            $mixPoolKey     = ShareMixPool::poolKey($task);
            $overproduced   = $isRecordedInKg || $mixPoolKey ? 0 : round((float) $modelData['quantity_made'] - $left, 3);
            $authorisedBy   = null;
            if ($overproduced > 0 && !$session->is_combined) {
                $this->refusePastPreviousStepOfCarriedJob($task, (float) $modelData['quantity_made']);
            }
            if ($overproduced > 0) {
                if (empty($modelData['manager_code'])) {
                    throw ValidationException::withMessages([
                        'quantity_made' => __('Only :left left on this task, a manager must authorise the extra :extra', ['left' => $left, 'extra' => $overproduced]),
                    ]);
                }
                $authorisedBy = $this->overproductionManager($session, $modelData['manager_code']);
            }

            if (($modelData['outcome'] ?? null) === 'complete' && empty($modelData['loose_confirmed']) && !$session->is_combined && !$mixPoolKey && !$isRecordedInKg) {
                $this->refuseLoosePieces($task, (float) $modelData['quantity_made']);
            }

            if ($mixPoolKey) {
                $session->fill([
                    'quantity_made' => $modelData['quantity_made'],
                    'is_combined'   => true,
                    'mix_pool_key'  => $mixPoolKey,
                ]);
                $shares  = ShareMixPool::make()->shareOut($session, $task, $mixPoolKey);
                $members = $shares->map(fn ($share) => $share->jobOrderItemTask)->prepend($task)->unique('id')->values();
            } else {
                $shares = $session->is_combined ? $this->shareOut($session, $members, $modelData, !$isRecordedInKg) : collect();
            }

            $session->fill([
                'quantity_made'                   => $modelData['quantity_made'],
                'quantity_rejected'               => $modelData['quantity_rejected'] ?? 0,
                'ended_at'                        => $endedAt,
                'state'                           => ManufactureTaskSessionStateEnum::CLOSED,
                'activity_type'                   => $modelData['activity_type'] ?? $session->activity_type ?? ManufactureTaskSessionActivityTypeEnum::PRODUCTION,
                'non_productive_reason'           => $modelData['non_productive_reason'] ?? null,
                'standard_rate'                   => $session->recipeStandardRate(),
                'raw_material_batches'            => $this->rawMaterialBatches($session, $members, $modelData),
            ]);
            if ($session->activity_type == ManufactureTaskSessionActivityTypeEnum::PRODUCTION && $task->jobOrderItem->artefact->isPaidAsDevelopment()) {
                $session->activity_type = ManufactureTaskSessionActivityTypeEnum::DEVELOPMENT;
            }
            $payType = $session->employee?->artisan_pay_type ?? ArtisanPayTypeEnum::PIECE_RATE;
            if (!$isQuantityOnly && $this->isImpossibleRate($session)) {
                if (empty($modelData['manager_code'])) {
                    throw ValidationException::withMessages([
                        'rate' => __(':made in :minutes min is :rate per hour against a target of :target, a manager must confirm it', [
                            'made'    => (float) $session->quantity_made,
                            'minutes' => round($session->paidHours() * 60, 1),
                            'rate'    => number_format((float) $session->quantity_made / max($session->paidHours(), 1 / 3600)),
                            'target'  => (float) $session->standard_rate,
                        ]),
                    ]);
                }
                $this->overproductionManager($session, $modelData['manager_code']);
            } elseif (!$isQuantityOnly && $this->isUncheckedHourlyPieceWork($session, $payType)) {
                if (empty($modelData['manager_code'])) {
                    throw ValidationException::withMessages([
                        'rate' => __(':made on :step, a step with no target, by someone paid by the hour: a manager must confirm it', [
                            'made' => (float) $session->quantity_made,
                            'step' => $session->stepName(),
                        ]),
                    ]);
                }
                $this->overproductionManager($session, $modelData['manager_code']);
            }
            $this->applyPayTerms($session, $payType);
            $session->save();

            if ($mixPoolKey) {
                TakeMixRawMaterialsFromStock::run($session, $task->recipeStep);
            }

            foreach ($members as $member) {
                $madeHere = $session->is_combined ? (float) ($shares->get($member->id)?->quantity_made ?? 0) : (float) $modelData['quantity_made'];

                if ($authorisedBy) {
                    $memberLeft = max(0, (float) $member->quantity_required - (float) $member->quantity_made);
                    $extra      = round($madeHere - $memberLeft, 3);
                    if ($extra > 0) {
                        $takenOff = $this->growJobOrderItem($member, (float) $member->quantity_made + $madeHere);
                        $this->recordOverproduction($session, $member, $extra, $authorisedBy, $modelData['manager_method'] ?? 'pin', $takenOff);
                    }
                }

                $member = CalculateJobOrderItemTaskQuantities::run($member);

                $outcome = $modelData['outcome'] ?? null;
                if ($outcome && $member->state != JobOrderItemTaskStateEnum::DONE && (!$mixPoolKey || $member->id == $task->id)) {
                    SettleShortJobOrderItemTask::run($member, $outcome === 'carry_over');
                }

                FillToOrder::make()->release($member->jobOrderItem);
            }

            if ($this->isLeftOverFromOldRecipe($session, $task)) {
                $session->delete();
                $task->delete();
                BroadcastManufactureFloorChanged::dispatch($session->production_id);

                return $session;
            }

            CalculateManufactureTaskSessionPay::run($session);

            return $session;
        });
    }

    /**
     * Once a job is carried, the rest of it is made and entered on the other part, so a step that
     * goes past what the step before it made on this part holds the other part's goods: no manager
     * can authorise that, it is entered where those goods were made.
     */
    private function refusePastPreviousStepOfCarriedJob(JobOrderItemTask $task, float $quantityMade): void
    {
        $item     = $task->jobOrderItem;
        $jobOrder = $task->jobOrder;
        $root     = Arr::get($jobOrder->data, 'carried_from');

        if ($task->isRecordedInKg() || (!$root && !Arr::get($item->data, 'carried_to'))) {
            return;
        }

        $waitsFor = $task->recipeStep?->waits_for;
        $previous = $item->tasks
            ->where('position', '<', $task->position)
            ->when($waitsFor !== null, fn (Collection $steps) => $steps->whereIn('artefact_manufacture_task_id', $waitsFor))
            ->reject(fn (JobOrderItemTask $step) => $step->isRecordedInKg())
            ->sortByDesc('position')
            ->first();

        if (!$previous || $task->artefactsIn((float) $task->quantity_made + $quantityMade) <= $previous->artefactsIn()) {
            return;
        }

        $root ??= $jobOrder->id;
        $otherParts = JobOrder::where(fn ($query) => $query->where('id', $root)->orWhere('data->carried_from', $root))
            ->where('id', '!=', $jobOrder->id)
            ->whereIn('state', JobOrderStateEnum::open())
            ->orderBy('id')
            ->pluck('reference');

        throw ValidationException::withMessages([
            'quantity_made' => __(':step made :made on :reference, this step cannot go past that. Enter the rest on :others, where it was made', [
                'step'      => $previous->stepName(),
                'made'      => $previous->artefactsIn(),
                'reference' => $jobOrder->reference,
                'others'    => $otherParts->isNotEmpty() ? $otherParts->join(', ') : __('the other part of this job'),
            ]),
        ]);
    }

    /**
     * A job finished short is closed in whole packs (SettleShortJobOrderItemTask::wholePacks), so the
     * artisan is told first how many pieces that leaves loose and can make the pack up instead.
     * Saving again with loose_confirmed closes it (HELP-4673). A line that shares packs with other
     * lines of its order is closed as made (JobOrderItem::sharesPacks).
     */
    private function refuseLoosePieces(JobOrderItemTask $task, float $quantityMade): void
    {
        $item     = $task->jobOrderItem;
        $packedIn = $item->artefact->packedIn();
        $pieces   = (int) $task->artefactsIn((float) $task->quantity_made + $quantityMade);
        $loose    = $pieces % $packedIn;
        if ($packedIn < 2 || $pieces < $packedIn || !$loose || $pieces >= $item->quantity || $item->sharesPacks()) {
            return;
        }

        $replacements = [
            'pieces' => $pieces,
            'packs'  => intdiv($pieces, $packedIn),
            'size'   => $packedIn,
            'loose'  => $loose,
            'more'   => $packedIn - $loose,
            'next'   => intdiv($pieces, $packedIn) + 1,
            'whole'  => $pieces - $loose,
        ];

        throw ValidationException::withMessages([
            'loose' => __(':pieces made = :packs packs of :size + :loose loose. Make :more more for :next packs, or finish at :whole and leave :loose loose?', $replacements),
        ]);
    }

    /**
     * @return array{preparation_reason_id?: int, preparation_reason_note?: string|null}
     */
    private function preparationReason(ManufactureTaskSession $session, array $modelData): array
    {
        if ($session->activity_type != ManufactureTaskSessionActivityTypeEnum::PREPARATION || empty($modelData['preparation_reason_id'])) {
            return [];
        }

        $reason = ManufacturePreparationReason::find($modelData['preparation_reason_id']);
        $note   = trim($modelData['preparation_reason_note'] ?? '');

        return [
            'preparation_reason_id'   => $reason->id,
            'preparation_reason_note' => $reason->is_other ? $note : null,
        ];
    }

    /**
     * A session left open on a step while the recipe dropped that step, and closed with nothing made, is the only
     * thing keeping the step on the job, so both go and the job holds just the recipe it has now.
     */
    private function isLeftOverFromOldRecipe(ManufactureTaskSession $session, JobOrderItemTask $task): bool
    {
        return !$session->is_combined
            && (float) $session->quantity_made == 0
            && (float) $task->quantity_made == 0
            && $task->combined_task_id === null
            && !$task->isInRecipe()
            && $task->sessions()->whereKeyNot($session->id)->doesntExist()
            && $task->sessionShares()->doesntExist();
    }

    /**
     * @return Collection<int, JobOrderItemTask>
     */
    private function combinedMembers(JobOrderItemTask $task): Collection
    {
        $members = $task->combinedGroup()
            ->reject(fn (JobOrderItemTask $member) => $member->state == JobOrderItemTaskStateEnum::DONE && $member->id != $task->id)
            ->map(fn (JobOrderItemTask $member) => JobOrderItemTask::lockForUpdate()->find($member->id));

        return $members->contains('id', $task->id) ? $members->values() : $members->prepend($task)->values();
    }

    /**
     * Each line gets the part of the batch it asked for; whole units stay whole.
     *
     * @param Collection<int, JobOrderItemTask> $members
     * @return Collection<int, ManufactureTaskSessionShare> keyed by task id
     */
    private function shareOut(ManufactureTaskSession $session, Collection $members, array $modelData, bool $wholeUnits = true): Collection
    {
        $weights  = ManufactureTaskSession::combinedWeights($members);
        $made     = self::apportion((float) $modelData['quantity_made'], $weights, $wholeUnits);
        $rejected = self::apportion((float) ($modelData['quantity_rejected'] ?? 0), $weights, $wholeUnits);

        $session->shares()->delete();
        $shares = $members->mapWithKeys(fn (JobOrderItemTask $member) => [
            $member->id => $session->shares()->create([
                'job_order_item_task_id' => $member->id,
                'share'                  => round($weights[$member->id], 6),
                'quantity_made'          => $made[$member->id],
                'quantity_rejected'      => $rejected[$member->id],
            ]),
        ]);
        $session->unsetRelation('shares');

        return $shares;
    }

    /**
     * Splits a quantity by weight. Whole quantities are split into whole units by largest remainder,
     * anything else, and every quantity weighed in kg, to three decimals, with the rounding left on the last line.
     *
     * @param Collection<int, float> $weights fractions summing to 1, keyed by task id
     * @return array<int, float>
     */
    public static function apportion(float $quantity, Collection $weights, bool $wholeUnits = true): array
    {
        if ($wholeUnits && $quantity == floor($quantity)) {
            $exact  = $weights->map(fn (float $weight) => $quantity * $weight);
            $shares = $exact->map(fn (float $share) => floor($share + 1e-9));
            $spare  = (int) round($quantity - $shares->sum());
            foreach ($exact->map(fn (float $share, int $id) => $share - $shares[$id])->sortDesc()->keys()->take($spare) as $id) {
                $shares[$id] += 1;
            }

            return $shares->all();
        }

        $shares = $weights->map(fn (float $weight) => round($quantity * $weight, 3));
        $lastId = $shares->keys()->last();
        $shares[$lastId] = round($quantity - $shares->except($lastId)->sum(), 3);

        return $shares->all();
    }

    /**
     * The manager's badge QR carries their clocking pin verbatim, so a scan and a typed pin are the
     * same check: the pin must belong to someone who may run this production's floor.
     */
    private function overproductionManager(ManufactureTaskSession $session, string $managerCode): Employee
    {
        $attemptsKey = 'overproduction-manager-code:'.$session->user_id;
        if (RateLimiter::tooManyAttempts($attemptsKey, 5)) {
            throw ValidationException::withMessages([
                'manager_code' => __('Too many wrong badges or PINs, try again in :minutes minutes', ['minutes' => ceil(RateLimiter::availableIn($attemptsKey) / 60)]),
            ]);
        }

        try {
            $employee = $this->resolveEmployeeByCode($session->production, $managerCode, __('This badge or PIN cannot authorise overproduction'));
        } catch (Exception $e) {
            RateLimiter::hit($attemptsKey, 900);
            throw ValidationException::withMessages(['manager_code' => $e->getMessage()]);
        }

        if ($employee->id == $session->employee_id || $employee->user_id == $session->user_id || $employee->users()->whereKey($session->user_id)->exists()) {
            throw ValidationException::withMessages([
                'manager_code' => __('Someone else must authorise your overproduction'),
            ]);
        }

        $managerUser = $employee->state == EmployeeStateEnum::WORKING ? ($employee->user ?? $employee->users()->first()) : null;
        if (!$managerUser?->authTo([
            'org-supervisor.'.$session->organisation_id,
            "productions_operations.$session->production_id.orchestrate",
        ])) {
            RateLimiter::hit($attemptsKey, 900);
            throw ValidationException::withMessages([
                'manager_code' => __('This badge or PIN cannot authorise overproduction'),
            ]);
        }

        return $employee;
    }

    /**
     * The job grows to what was really made, so receiving, raw material deduction and the surplus
     * routing all read the new size: whatever the lines did not ask for goes to stock.
     * Only whole artefacts grow the job; later steps grow with it, earlier steps are not reopened.
     */
    /**
     * @return array<int, int> artefacts taken off the order's other lines, by line id
     */
    private function growJobOrderItem(JobOrderItemTask $task, float $taskUnitsMade): array
    {
        $grownBy = $this->growLine($task, $taskUnitsMade, (int) $task->artefactsIn($taskUnitsMade));

        return $grownBy > 0 ? $this->takeOffRestOfOrder($task->jobOrderItem, $grownBy) : [];
    }

    /**
     * The step asks for what was made on it and the line grows to $artefacts; later steps grow with it,
     * earlier steps are not reopened. Returns how many artefacts the line grew by.
     */
    public function growLine(JobOrderItemTask $task, float $taskUnitsMade, int $artefacts): int
    {
        $task->setRequiredQuantity(max((float) $task->quantity_required, $taskUnitsMade));

        $item = $task->jobOrderItem;
        if ($artefacts <= $item->quantity) {
            return 0;
        }

        $grownBy = $artefacts - $item->quantity;
        $item->update(['quantity' => $artefacts]);

        $laterSteps = $item->tasks->skipUntil(fn (JobOrderItemTask $step) => $step->id == $task->id)->skip(1);
        foreach ($laterSteps as $step) {
            $step->setRequiredQuantity(max($step->requiredFor($artefacts), (float) $step->quantity_required));
        }

        return $grownBy;
    }

    /**
     * What a line made past its share was owed by the rest of its order, so it comes off the order's
     * other open lines, newest first, with the To Produce lines it covers (HELP-4509): the order still
     * asks for what it ordered, not that plus the extra. With no open line left it is simply extra.
     * A line someone is working on, alone or in a combined batch, is left as it is.
     * ponytail: a line is never taken below 1, a line the extra covers whole is left with 1 to close by hand.
     *
     * @return array<int, int> artefacts taken off, by line id
     */
    private function takeOffRestOfOrder(JobOrderItem $item, int $grownBy): array
    {
        $openLines = $item->orderItems()
            ->reject(fn (JobOrderItem $line) => $line->id == $item->id
                || Arr::has($line->data ?? [], 'carried_to')
                || (float) $line->quantity_received > 0
                || !in_array($line->jobOrder->state, JobOrderStateEnum::open())
                || $line->tasks->contains(fn (JobOrderItemTask $step) => $step->combined_task_id !== null)
                || ManufactureTaskSession::whereIn('job_order_item_task_id', $line->tasks->pluck('id'))->where('state', ManufactureTaskSessionStateEnum::OPEN)->exists())
            ->sortByDesc('id');

        $takenOffByLine = [];

        foreach ($openLines as $line) {
            $made     = (int) $line->tasks->reject(fn (JobOrderItemTask $step) => $step->isRecordedInKg())->map(fn (JobOrderItemTask $step) => $step->artefactsIn())->max();
            $takenOff = min($grownBy, $line->quantity - max($made, 1));
            if ($takenOff <= 0) {
                continue;
            }

            $line->update(['quantity' => $line->quantity - $takenOff]);
            foreach ($line->tasks as $step) {
                $floor = $step->isRecordedInKg() ? 0 : (float) $step->quantity_made;
                $step->setRequiredQuantity(max($floor, round((float) $step->quantity_required - $step->requiredFor($takenOff), 3)));
            }
            $this->moveToProduceLines($line, $line->job_order_id, $item->job_order_id, $takenOff);

            $takenOffByLine[$line->id] = $takenOff;
            $grownBy -= $takenOff;
            if ($grownBy <= 0) {
                break;
            }
        }

        return $takenOffByLine;
    }

    /**
     * A correction that takes back authorised extra gives the order's other lines back what it took off them,
     * as far as they are still open, so the order keeps what it ordered.
     *
     * @param  array<int|string, int>  $takenOffByLine
     * @return array<int|string, int> what is still taken off, by line id
     */
    public function giveBackToRestOfOrder(JobOrderItem $item, array $takenOffByLine, int $artefacts): array
    {
        $lines = JobOrderItem::whereIn('id', array_keys($takenOffByLine))->with(['tasks', 'jobOrder'])->orderBy('id')->get()
            ->reject(fn (JobOrderItem $line) => Arr::has($line->data ?? [], 'carried_to') || !in_array($line->jobOrder->state, JobOrderStateEnum::open()));

        foreach ($lines as $line) {
            $givenBack = min($artefacts, (int) $takenOffByLine[$line->id]);
            if ($givenBack <= 0) {
                break;
            }

            $line->update(['quantity' => $line->quantity + $givenBack]);
            foreach ($line->tasks as $step) {
                $step->setRequiredQuantity(round((float) $step->quantity_required + $step->requiredFor($givenBack), 3));
            }
            $this->moveToProduceLines($line, $item->job_order_id, $line->job_order_id, $givenBack);
            $takenOffByLine[$line->id] -= $givenBack;
            $artefacts -= $givenBack;
        }

        return array_filter($takenOffByLine);
    }

    private function moveToProduceLines(JobOrderItem $line, int $fromJobOrderId, int $toJobOrderId, int $artefacts): void
    {
        if ($fromJobOrderId == $toJobOrderId) {
            return;
        }

        $skos  = round($artefacts / $line->artefact->packedIn(), 3);
        $lines = PartnerShoppingListItem::where('job_order_id', $fromJobOrderId)
            ->where('stock_id', $line->artefact->orgStock?->stock_id)
            ->orderByDesc('id')
            ->get();

        foreach ($lines as $line) {
            $wanted = $line->claimedSkos();
            $moved  = min($skos, $wanted);
            $line->moveUncoveredTo(round($wanted - $moved, 3), $toJobOrderId);
            $skos = round($skos - $moved, 3);
            if ($skos <= 0) {
                return;
            }
        }
    }

    /**
     * @param  array<int, int>  $takenOffByLine
     */
    private function recordOverproduction(ManufactureTaskSession $session, JobOrderItemTask $task, float $overproduced, Employee $manager, string $method, array $takenOffByLine): void
    {
        $jobOrder = $task->jobOrder;
        $data     = $jobOrder->data ?? [];

        $data['overproductions'][] = [
            'session_id'    => $session->id,
            'step'          => $task->stepName(),
            'artefact_code' => $task->jobOrderItem->artefact->code,
            'quantity'      => $overproduced,
            'pack_size'     => $task->packSize(),
            'made_by'       => $session->user->contact_name ?: $session->user->username,
            'manager'       => $manager->contact_name,
            'manager_id'    => $manager->id,
            'method'        => $method,
            'at'            => now()->toIso8601String(),
        ] + ($takenOffByLine ? ['taken_off_lines' => $takenOffByLine] : []);

        $jobOrder->update(['data' => $data]);
    }

    /**
     * The step sets the piece rate and reward terms; an hourly artisan earns neither, whatever step they pick.
     */
    public function applyPayTerms(ManufactureTaskSession $session, ArtisanPayTypeEnum $payType): void
    {
        $manufactureTask = $session->manufactureTask;
        $isPieceRate     = $payType == ArtisanPayTypeEnum::PIECE_RATE && $manufactureTask?->is_piece_rate;

        $session->fill([
            'artisan_pay_type'                => $payType,
            'task_work_cost'                  => $isPieceRate ? $manufactureTask->task_work_cost : 0,
            'operative_reward_terms'          => $payType == ArtisanPayTypeEnum::HOURLY ? ManufactureTaskOperativeRewardTermsEnum::NEVER->value : $manufactureTask?->operative_reward_terms,
            'operative_reward_allowance_type' => $payType == ArtisanPayTypeEnum::HOURLY ? null : $manufactureTask?->operative_reward_allowance_type,
            'operative_reward_amount'         => $isPieceRate ? $manufactureTask->operative_reward_amount : 0,
        ]);
        $session->is_under_target = $this->isUnderTarget($session);
    }

    /**
     * Ten times the step's target never happens on the floor (real sessions top out near six), so it is
     * a quantity tapped in for work not done in this session, like a step closed seconds after it opened.
     */
    public function isImpossibleRate(ManufactureTaskSession $session): bool
    {
        if ($session->activity_type != ManufactureTaskSessionActivityTypeEnum::PRODUCTION || !$session->standard_rate || (float) $session->quantity_made <= 0) {
            return false;
        }

        return (float) $session->quantity_made > 10 * (float) $session->standard_rate * $session->paidHours();
    }

    /**
     * Without a target the speed cannot be checked, and an hourly artisan on a piece-rate step is the
     * one who recorded a mix on Making when the job had no mix step yet.
     */
    private function isUncheckedHourlyPieceWork(ManufactureTaskSession $session, ArtisanPayTypeEnum $payType): bool
    {
        return $payType == ArtisanPayTypeEnum::HOURLY
            && $session->activity_type == ManufactureTaskSessionActivityTypeEnum::PRODUCTION
            && $session->manufactureTask?->is_piece_rate
            && $session->standard_rate === null
            && (float) $session->quantity_made > 0;
    }

    public function isUnderTarget(ManufactureTaskSession $session): bool
    {
        if ($session->artisan_pay_type == ArtisanPayTypeEnum::HOURLY) {
            return false;
        }

        if ($session->activity_type != ManufactureTaskSessionActivityTypeEnum::PRODUCTION || $session->standard_rate === null) {
            return false;
        }

        $hours = $session->paidHours();
        if ($hours <= 0) {
            return false;
        }

        return (float) $session->quantity_made / $hours < (float) $session->standard_rate;
    }

    /**
     * The batch code and expiry date the artisan read on each raw material's packaging (HELP-4699), only in a
     * production that asks for them, and only for raw materials on the recipe of the step being closed.
     *
     * @param  Collection<int, JobOrderItemTask>  $members
     * @return array<int, array{raw_material_id: int, code: string, name: string, batch_code: string|null, expiry_date: string|null}>|null
     */
    private function rawMaterialBatches(ManufactureTaskSession $session, Collection $members, array $modelData): ?array
    {
        $entered = $modelData['raw_material_batches'] ?? null;
        if (!$entered || !$session->production->recordsRawMaterialBatches()) {
            return null;
        }

        $onRecipe = $members->flatMap(fn (JobOrderItemTask $member) => $member->recipeStep?->rawMaterials ?? collect())
            ->map(fn (RecipeStepRawMaterial $line) => $line->rawMaterial)
            ->filter()
            ->keyBy('id');

        $batches = [];
        foreach ($entered as $row) {
            $batchCode  = trim((string) ($row['batch_code'] ?? ''));
            $expiryDate = $row['expiry_date'] ?? null;
            if ($batchCode === '' && !$expiryDate) {
                continue;
            }

            $rawMaterial = $onRecipe->get((int) $row['raw_material_id']);
            if (!$rawMaterial) {
                throw ValidationException::withMessages([
                    'raw_material_batches' => __('This raw material is not on the recipe of this step'),
                ]);
            }

            $batches[] = [
                'raw_material_id' => $rawMaterial->id,
                'code'            => $rawMaterial->code,
                'name'            => $rawMaterial->description,
                'batch_code'      => $batchCode === '' ? null : $batchCode,
                'expiry_date'     => $expiryDate,
            ];
        }

        return $batches ?: null;
    }

    public function rules(): array
    {
        return [
            'quantity_made'          => [Rule::requiredIf(fn () => !$this->manufactureTaskSession->isNonProductive()), 'nullable', 'numeric', 'min:0'],
            'quantity_rejected'      => ['sometimes', 'numeric', 'min:0'],
            'outcome'                => ['sometimes', 'nullable', Rule::in(['complete', 'carry_over'])],
            'loose_confirmed'        => ['sometimes', 'boolean'],
            'raw_material_batches'                   => ['sometimes', 'nullable', 'array', 'max:50'],
            'raw_material_batches.*.raw_material_id' => ['required', 'integer'],
            'raw_material_batches.*.batch_code'      => ['nullable', 'string', 'max:64'],
            'raw_material_batches.*.expiry_date'     => ['nullable', 'date_format:Y-m-d'],
            'manager_code'           => ['sometimes', 'nullable', 'string', 'max:64'],
            'manager_method'         => ['sometimes', 'nullable', Rule::in(['qr', 'pin'])],
            'finished_before_shift_end' => ['sometimes', 'boolean'],
            'activity_type'          => ['sometimes', Rule::enum(ManufactureTaskSessionActivityTypeEnum::class)->except(ManufactureTaskSessionActivityTypeEnum::floorActivities())],
            'preparation_reason_id'   => [
                Rule::requiredIf(fn () => $this->manufactureTaskSession->activity_type == ManufactureTaskSessionActivityTypeEnum::PREPARATION
                    && !$this->manufactureTaskSession->isShortPreparation(now())),
                'nullable',
                'integer',
                Rule::exists('manufacture_preparation_reasons', 'id')->where('production_id', $this->manufactureTaskSession->production_id)->whereNull('deleted_at'),
            ],
            'preparation_reason_note' => [
                Rule::requiredIf(fn () => $this->manufactureTaskSession->activity_type == ManufactureTaskSessionActivityTypeEnum::PREPARATION
                    && ManufacturePreparationReason::whereKey($this->get('preparation_reason_id'))->where('is_other', true)->exists()),
                'nullable',
                'string',
                'max:500',
            ],
            'non_productive_reason'  => [
                Rule::requiredIf(function () {
                    $activityType = $this->get('activity_type');

                    return $activityType && $activityType != ManufactureTaskSessionActivityTypeEnum::PRODUCTION->value;
                }),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'preparation_reason_id.required'   => __('Choose why you needed preparation'),
            'preparation_reason_id.exists'     => __('This reason is no longer on the list, choose another'),
            'preparation_reason_note.required' => __('Type the reason'),
            'raw_material_batches.*.batch_code.max'          => __('A batch code can have up to 64 characters'),
            'raw_material_batches.*.expiry_date.date_format' => __('Pick the expiry date from the calendar'),
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->id == $this->manufactureTaskSession->user_id
            || $request->user()->authTo([
                'org-supervisor.'.$this->organisation->id,
                "productions_operations.{$this->production->id}.orchestrate",
            ]);
    }

    private ManufactureTaskSession $manufactureTaskSession;

    public function action(ManufactureTaskSession $session, array $modelData): ManufactureTaskSession
    {
        $this->asAction               = true;
        $this->manufactureTaskSession = $session;
        $this->initialisation($session->organisation, $modelData);

        return $this->handle($session, $this->validatedData);
    }

    public function asController(ManufactureTaskSession $manufactureTaskSession, ActionRequest $request): ManufactureTaskSession
    {
        $this->manufactureTaskSession = $manufactureTaskSession;
        $this->initialisationFromProduction($manufactureTaskSession->production, $request);

        $modelData = $this->validatedData;
        if (!ShowManufactureFloor::canPickOpenJobs($request->user(), $manufactureTaskSession->production)) {
            unset($modelData['quantity_rejected']);
        }

        return $this->handle($manufactureTaskSession, $modelData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
