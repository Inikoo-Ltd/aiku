<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 Oct 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\OrgAction;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\ManufactureTaskSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UndoManufacturePreparationMove extends OrgAction
{
    use WithManufacturePayrollLock;

    /**
     * Short preparation added to the step started after it goes back to being Preparation, and the step starts when it first did.
     */
    public function handle(ManufactureTaskSession $preparation, array $modelData = []): ManufactureTaskSession
    {
        return DB::transaction(function () use ($preparation, $modelData) {
            $preparation = ManufactureTaskSession::lockForUpdate()->find($preparation->id);
            $job         = $preparation->moved_into_session_id ? ManufactureTaskSession::lockForUpdate()->find($preparation->moved_into_session_id) : null;

            if (!$job || $preparation->state != ManufactureTaskSessionStateEnum::VOIDED) {
                throw ValidationException::withMessages([
                    'state' => __('This preparation was not added to a job step'),
                ]);
            }
            if (!$job->started_at->equalTo($preparation->started_at)) {
                throw ValidationException::withMessages([
                    'state' => __('The times of the job step were changed since, edit its times instead'),
                ]);
            }

            return $this->changeInPayWeek($preparation->production, $job, $job->user_id, 'keep_as_preparation', [$preparation->started_at, $job->ended_at], ['started_at', 'hours', 'band_code', 'pay'], $modelData['lock_reason'] ?? null, function () use ($preparation, $job) {
                $job->started_at = $preparation->started_at->copy()->addSeconds($preparation->moved_seconds);
                if ($job->state == ManufactureTaskSessionStateEnum::CLOSED) {
                    EditManufactureTaskSessionTimes::make()->recalculate($job);
                } else {
                    $job->save();
                }

                $preparation->update([
                    'state'                 => ManufactureTaskSessionStateEnum::CLOSED,
                    'moved_into_session_id' => null,
                    'moved_seconds'         => null,
                ]);

                return $preparation;
            });
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
            "accounting.{$this->organisation->id}.edit",
        ]);
    }

    public function rules(): array
    {
        return [
            'lock_reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function action(ManufactureTaskSession $preparation, ?User $editor = null, array $modelData = []): ManufactureTaskSession
    {
        $this->asAction      = true;
        $this->payrollEditor = $editor;
        $this->initialisationFromProduction($preparation->production, $modelData);

        return $this->handle($preparation, $this->validatedData);
    }

    public function asController(ManufactureTaskSession $manufactureTaskSession, ActionRequest $request): ManufactureTaskSession
    {
        $this->payrollEditor = $request->user();
        $this->initialisationFromProduction($manufactureTaskSession->production, $request);

        return $this->handle($manufactureTaskSession, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
