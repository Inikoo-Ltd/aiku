<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 13:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\User\GetUserCurrentEmployee;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\JobOrderItem;
use App\Models\Production\ManufactureBreak;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StartManufactureNonProductiveSession extends OrgAction
{
    /**
     * @param array{activity_type: string, job_order_id?: int|null} $modelData
     */
    public function handle(User $user, Production $production, array $modelData): ManufactureTaskSession
    {
        $hasOpenSession = ManufactureTaskSession::where('user_id', $user->id)
            ->where('state', ManufactureTaskSessionStateEnum::OPEN)
            ->exists();
        if ($hasOpenSession) {
            throw ValidationException::withMessages([
                'activity_type' => __('Finish what you are working on first'),
            ]);
        }
        if (ManufactureBreak::where('user_id', $user->id)->open()->exists()) {
            throw ValidationException::withMessages([
                'activity_type' => __('Finish your break first'),
            ]);
        }

        try {
            return ManufactureTaskSession::create([
                'group_id'        => $production->group_id,
                'organisation_id' => $production->organisation_id,
                'production_id'   => $production->id,
                'job_order_id'    => $modelData['job_order_id'] ?? null,
                'activity_type'   => $modelData['activity_type'],
                'user_id'         => $user->id,
                'employee_id'     => GetUserCurrentEmployee::run($user, $production->organisation_id)?->id,
                'state'           => ManufactureTaskSessionStateEnum::OPEN,
                'started_at'      => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'activity_type' => __('Finish what you are working on first'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'activity_type' => ['required', Rule::in(array_map(fn (ManufactureTaskSessionActivityTypeEnum $activity) => $activity->value, ManufactureTaskSessionActivityTypeEnum::floorActivities()))],
            'job_order_id'  => [
                Rule::requiredIf(fn () => $this->get('activity_type') == ManufactureTaskSessionActivityTypeEnum::MAINTENANCE->value),
                'nullable',
                'integer',
                Rule::exists('job_orders', 'id')->where('production_id', $this->production->id)->whereNull('deleted_at'),
            ],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty() || $this->get('activity_type') != ManufactureTaskSessionActivityTypeEnum::MAINTENANCE->value) {
            return;
        }

        $makesMaintainedProduct = JobOrderItem::where('job_order_id', $this->get('job_order_id'))
            ->whereHas('artefact', fn ($query) => $query->needsMachineMaintenance())
            ->exists();
        if (!$makesMaintainedProduct) {
            $validator->errors()->add('job_order_id', __('Maintenance is only for job orders of products made on a machine that needs cleaning'));
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function action(User $user, Production $production, array $modelData): ManufactureTaskSession
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($user, $production, $this->validatedData);
    }

    public function asController(Production $production, ActionRequest $request): ManufactureTaskSession
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($request->user(), $production, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
