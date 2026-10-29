<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 21:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\HumanResources\ClockingMachine\ResolvesEmployeeByCode;
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
use App\Models\Production\JobOrder;
use App\Models\Production\ManufactureBreak;
use App\Models\Production\ManufacturePreparationReason;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\ManufactureTaskSessionShare;
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
            $overproduced   = $isRecordedInKg ? 0 : round((float) $modelData['quantity_made'] - $left, 3);
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

            $shares = $session->is_combined ? $this->shareOut($session, $members, $modelData, !$isRecordedInKg) : collect();

            $session->fill([
                'quantity_made'                   => $modelData['quantity_made'],
                'quantity_rejected'               => $modelData['quantity_rejected'] ?? 0,
                'ended_at'                        => $endedAt,
                'state'                           => ManufactureTaskSessionStateEnum::CLOSED,
                'activity_type'                   => $modelData['activity_type'] ?? $session->activity_type ?? ManufactureTaskSessionActivityTypeEnum::PRODUCTION,
                'non_productive_reason'           => $modelData['non_productive_reason'] ?? null,
                'standard_rate'                   => $session->recipeStandardRate(),
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
                            'step' => $manufactureTask->name,
                        ]),
                    ]);
                }
                $this->overproductionManager($session, $modelData['manager_code']);
            }
            $this->applyPayTerms($session, $payType);
            $session->save();

            foreach ($members as $member) {
                $madeHere = $session->is_combined ? (float) $shares[$member->id]->quantity_made : (float) $modelData['quantity_made'];

                if ($authorisedBy) {
                    $memberLeft = max(0, (float) $member->quantity_required - (float) $member->quantity_made);
                    $extra      = round($madeHere - $memberLeft, 3);
                    if ($extra > 0) {
                        $this->growJobOrderItem($member, (float) $member->quantity_made + $madeHere);
                        $this->recordOverproduction($session, $member, $extra, $authorisedBy, $modelData['manager_method'] ?? 'pin');
                    }
                }

                $member = CalculateJobOrderItemTaskQuantities::run($member);

                $outcome = $modelData['outcome'] ?? null;
                if ($outcome && $member->state != JobOrderItemTaskStateEnum::DONE) {
                    SettleShortJobOrderItemTask::run($member, $outcome === 'carry_over');
                }
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

        $previous = $item->tasks
            ->where('position', '<', $task->position)
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
                'step'      => $previous->manufactureTask->name,
                'made'      => $previous->artefactsIn(),
                'reference' => $jobOrder->reference,
                'others'    => $otherParts->isNotEmpty() ? $otherParts->join(', ') : __('the other part of this job'),
            ]),
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
            && $task->jobOrderItem->artefact->manufactureTasks()->whereKey($task->manufacture_task_id)->doesntExist()
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
    private function growJobOrderItem(JobOrderItemTask $task, float $taskUnitsMade): void
    {
        $task->setRequiredQuantity(max((float) $task->quantity_required, $taskUnitsMade));

        $item      = $task->jobOrderItem;
        $artefacts = (int) $task->artefactsIn($taskUnitsMade);
        if ($artefacts <= $item->quantity) {
            return;
        }

        $item->update(['quantity' => $artefacts]);

        $laterSteps = $item->tasks->skipUntil(fn (JobOrderItemTask $step) => $step->id == $task->id)->skip(1);
        foreach ($laterSteps as $step) {
            $step->setRequiredQuantity(max($step->requiredFor($artefacts), (float) $step->quantity_required));
        }
    }

    private function recordOverproduction(ManufactureTaskSession $session, JobOrderItemTask $task, float $overproduced, Employee $manager, string $method): void
    {
        $jobOrder = $task->jobOrder;
        $data     = $jobOrder->data ?? [];

        $data['overproductions'][] = [
            'session_id'    => $session->id,
            'step'          => $task->manufactureTask->name,
            'artefact_code' => $task->jobOrderItem->artefact->code,
            'quantity'      => $overproduced,
            'pack_size'     => $task->packSize(),
            'made_by'       => $session->user->contact_name ?: $session->user->username,
            'manager'       => $manager->contact_name,
            'manager_id'    => $manager->id,
            'method'        => $method,
            'at'            => now()->toIso8601String(),
        ];

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

    public function rules(): array
    {
        return [
            'quantity_made'          => [Rule::requiredIf(fn () => !$this->manufactureTaskSession->isNonProductive()), 'nullable', 'numeric', 'min:0'],
            'quantity_rejected'      => ['sometimes', 'numeric', 'min:0'],
            'outcome'                => ['sometimes', 'nullable', Rule::in(['complete', 'carry_over'])],
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
