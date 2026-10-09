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
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Actions\Production\ManufactureBreak\EndManufactureBreak;
use App\Models\HumanResources\Employee;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\ManufactureBreak;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\ManufactureTaskSessionShare;
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
            CalculateManufactureTaskSessionBreakMinutes::run($session, now());

            if ($session->isNonProductive()) {
                $session->update([
                    'ended_at' => now(),
                    'state'    => ManufactureTaskSessionStateEnum::CLOSED,
                ]);

                return CalculateManufactureTaskSessionPay::run($session);
            }

            $manufactureTask = $session->manufactureTask;

            $task = JobOrderItemTask::lockForUpdate()->find($session->job_order_item_task_id);
            $session->setRelation('jobOrderItemTask', $task);
            $members = $session->is_combined ? $this->combinedMembers($task) : collect([$task]);

            $left         = $members->sum(fn (JobOrderItemTask $member) => max(0, (float) $member->quantity_required - (float) $member->quantity_made));
            $overproduced = round((float) $modelData['quantity_made'] - $left, 3);
            $authorisedBy = null;
            if ($overproduced > 0) {
                if (empty($modelData['manager_code'])) {
                    throw ValidationException::withMessages([
                        'quantity_made' => __('Only :left left on this task, a manager must authorise the extra :extra', ['left' => $left, 'extra' => $overproduced]),
                    ]);
                }
                $authorisedBy = $this->overproductionManager($session, $modelData['manager_code']);
            }

            $shares = $session->is_combined ? $this->shareOut($session, $members, $modelData) : collect();

            $session->fill([
                'quantity_made'                   => $modelData['quantity_made'],
                'quantity_rejected'               => $modelData['quantity_rejected'] ?? 0,
                'ended_at'                        => now(),
                'state'                           => ManufactureTaskSessionStateEnum::CLOSED,
                'task_work_cost'                  => $manufactureTask->is_piece_rate ? $manufactureTask->task_work_cost : 0,
                'operative_reward_terms'          => $manufactureTask->operative_reward_terms,
                'operative_reward_allowance_type' => $manufactureTask->operative_reward_allowance_type,
                'operative_reward_amount'         => $manufactureTask->is_piece_rate ? $manufactureTask->operative_reward_amount : 0,
                'activity_type'                   => $modelData['activity_type'] ?? $session->activity_type ?? ManufactureTaskSessionActivityTypeEnum::PRODUCTION,
                'non_productive_reason'           => $modelData['non_productive_reason'] ?? null,
                'standard_rate'                   => $session->recipeStandardRate(),
            ]);
            $session->is_under_target = $this->isUnderTarget($session);
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

            CalculateManufactureTaskSessionPay::run($session);

            return $session;
        });
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
    private function shareOut(ManufactureTaskSession $session, Collection $members, array $modelData): Collection
    {
        $weights  = ManufactureTaskSession::combinedWeights($members);
        $made     = self::apportion((float) $modelData['quantity_made'], $weights);
        $rejected = self::apportion((float) ($modelData['quantity_rejected'] ?? 0), $weights);

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
     * anything else to three decimals, with the rounding left on the last line.
     *
     * @param Collection<int, float> $weights fractions summing to 1, keyed by task id
     * @return array<int, float>
     */
    public static function apportion(float $quantity, Collection $weights): array
    {
        if ($quantity == floor($quantity)) {
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
        $task->update(['quantity_required' => max((float) $task->quantity_required, $taskUnitsMade)]);

        $item             = $task->jobOrderItem;
        $unitsPerArtefact = ArtefactManufactureTask::where('artefact_id', $item->artefact_id)
            ->pluck('units_per_artefact', 'manufacture_task_id');

        $artefacts = (int) floor($taskUnitsMade / max(0.001, (float) ($unitsPerArtefact[$task->manufacture_task_id] ?? 1)));
        if ($artefacts <= $item->quantity) {
            return;
        }

        $item->update(['quantity' => $artefacts]);

        $laterSteps = $item->tasks->skipUntil(fn (JobOrderItemTask $step) => $step->id == $task->id)->skip(1);
        foreach ($laterSteps as $step) {
            $step->update(['quantity_required' => max($artefacts * (float) ($unitsPerArtefact[$step->manufacture_task_id] ?? 1), (float) $step->quantity_required)]);
            CalculateJobOrderItemTaskQuantities::run($step);
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
            'made_by'       => $session->user->contact_name ?: $session->user->username,
            'manager'       => $manager->contact_name,
            'manager_id'    => $manager->id,
            'method'        => $method,
            'at'            => now()->toIso8601String(),
        ];

        $jobOrder->update(['data' => $data]);
    }

    private function isUnderTarget(ManufactureTaskSession $session): bool
    {
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
            'activity_type'          => ['sometimes', Rule::enum(ManufactureTaskSessionActivityTypeEnum::class)->except(ManufactureTaskSessionActivityTypeEnum::floorActivities())],
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
