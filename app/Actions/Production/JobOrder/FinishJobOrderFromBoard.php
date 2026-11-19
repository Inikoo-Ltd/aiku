<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026 16:00:00 Central European Standard Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrderItemTask\ShareMixPool;
use App\Actions\Production\ManufactureTaskSession\CloseManufactureTaskSession;
use App\Actions\Production\ManufactureTaskSession\StartManufactureTaskSession;
use App\Actions\SysAdmin\User\GetUserCurrentEmployee;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItem;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * A simple board (Production::hasSimpleBoard) is worked by one person without the floor tablet:
 * dropping a Producing card on Done records what is left of each step of the card's lines through the
 * floor's own Start and Close, as the person who dropped it, then receives them into the factory location.
 */
class FinishJobOrderFromBoard extends OrgAction
{
    public function handle(JobOrder $jobOrder, User $user, int $artefactId): JobOrder
    {
        $production = $jobOrder->production;

        if (!$production->hasSimpleBoard()) {
            throw ValidationException::withMessages(['job_order' => __('Only a simple board finishes jobs by moving them to Done')]);
        }

        if (!$production->factory_location_id) {
            throw ValidationException::withMessages(['job_order' => __('This factory has no location to receive the stock into')]);
        }

        return DB::transaction(function () use ($jobOrder, $user, $artefactId, $production) {
            $jobOrder = JobOrder::lockForUpdate()->find($jobOrder->id);

            if (!in_array($jobOrder->state, [JobOrderStateEnum::IN_PROCESS, JobOrderStateEnum::CONFIRMED])) {
                throw ValidationException::withMessages(['job_order' => __(':reference is not being made', ['reference' => $jobOrder->reference])]);
            }

            if ($hold = Arr::get($jobOrder->data, 'hold')) {
                throw ValidationException::withMessages(['job_order' => __('On hold: :reason. Clear the hold first.', ['reason' => $hold['reason']])]);
            }

            if ($jobOrder->isTrialRun() || $jobOrder->isRequestedByDevelopment()) {
                throw ValidationException::withMessages(['job_order' => __(':reference is a development job, finish it on its job order page', ['reference' => $jobOrder->reference])]);
            }

            if ($jobOrder->state == JobOrderStateEnum::IN_PROCESS) {
                ConfirmJobOrder::make()->action($jobOrder);
            }

            $lines = $jobOrder->jobOrderItems()->where('artefact_id', $artefactId)->with('tasks')->get();

            if ($lines->isEmpty() || $lines->contains(fn (JobOrderItem $line) => Arr::has($line->data, 'fill'))) {
                throw ValidationException::withMessages(['job_order' => __(':reference has nothing to finish here', ['reference' => $jobOrder->reference])]);
            }

            foreach ($lines as $line) {
                foreach ($line->tasks as $task) {
                    $this->recordWhatIsLeft($task, $user);
                }
            }

            ReceiveJobOrderIntoStock::make()->action($jobOrder->refresh(), [
                'location_id' => $production->factory_location_id,
                'user_id'     => $user->id,
                'allocations' => $lines->mapWithKeys(fn (JobOrderItem $line) => [$line->id => (float) $line->quantity])->all(),
            ]);

            return $jobOrder;
        });
    }

    private function recordWhatIsLeft(JobOrderItemTask $task, User $user): void
    {
        $task->refresh();
        if ($task->state == JobOrderItemTaskStateEnum::DONE) {
            return;
        }

        if ($task->combined_task_id || ShareMixPool::poolKey($task)) {
            throw ValidationException::withMessages(['job_order' => __(':step is made with other jobs, finish it on the floor', ['step' => $task->stepName()])]);
        }

        $session = ManufactureTaskSession::where('job_order_item_task_id', $task->id)
            ->where('user_id', $user->id)
            ->where('state', ManufactureTaskSessionStateEnum::OPEN)
            ->first() ?? StartManufactureTaskSession::make()->action($user, $task);

        $task->refresh();
        CloseManufactureTaskSession::make()->fromBoard()->action($session, [
            'quantity_made' => max(0, round((float) $task->quantity_required - (float) $task->quantity_made, 3)),
        ]);
    }

    public function rules(): array
    {
        return [
            'cards'                => ['required', 'list', 'min:1', 'max:50'],
            'cards.*.job_order_id' => ['required', 'integer'],
            'cards.*.artefact_id'  => ['required', 'integer'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($request->user()->authTo(['org-supervisor.'.$this->organisation->id, "productions_operations.{$this->production->id}.orchestrate"])) {
            return true;
        }

        $employeeId = GetUserCurrentEmployee::run($request->user(), $this->organisation->id)?->id;

        return $employeeId
            && $request->user()->authTo("productions_operations.{$this->production->id}.prepare")
            && !JobOrder::whereIn('id', collect($request->input('cards'))->pluck('job_order_id')->map(fn ($id) => (int) $id))
                ->where(fn ($query) => $query->whereNull('employee_id')->orWhere('employee_id', '!=', $employeeId))
                ->exists();
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): void
    {
        $this->initialisationFromProduction($production, $request);

        DB::transaction(function () use ($production, $request) {
            foreach ($this->validatedData['cards'] as $card) {
                $this->handle(JobOrder::where('production_id', $production->id)->findOrFail($card['job_order_id']), $request->user(), $card['artefact_id']);
            }
        });
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
