<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Oct 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureArtisanDay;

use App\Actions\OrgAction;
use App\Actions\Production\ManufactureTaskSession\WithManufacturePayrollLock;
use App\Enums\Production\ManufactureArtisanDay\ManufactureArtisanDayResolutionEnum;
use App\Enums\Production\ManufactureArtisanDay\ManufactureArtisanDayShortReasonEnum;
use App\Models\Production\ManufactureArtisanDay;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * A supervisor's answer to a day over or under the shift, or with no break clocked. Not authorising overtime leaves the hours in payroll: it only
 * marks them for the accountant and HR, with the reason.
 */
class ResolveManufactureArtisanDay extends OrgAction
{
    use WithManufacturePayrollLock;

    public function handle(ManufactureArtisanDay $day, User $user, array $modelData): ManufactureArtisanDay
    {
        $resolution = ManufactureArtisanDayResolutionEnum::from($modelData['resolution']);
        if (!$resolution->answers($day->status)) {
            throw ValidationException::withMessages([
                'resolution' => __('This day is not flagged for this any more'),
            ]);
        }

        $this->payrollEditor ??= $user;
        $dayStart = Carbon::parse($day->date->toDateString());
        $this->changeInPayWeek($day->production, $day, $day->user_id, 'daily_hours', [$dayStart], ['resolution', 'resolution_reason', 'resolution_note'], $modelData['lock_reason'] ?? null, function () use ($day, $resolution, $modelData, $user) {
            $day->fill([
                'resolution'          => $resolution,
                'resolution_reason'   => $resolution == ManufactureArtisanDayResolutionEnum::REASON_GIVEN ? $modelData['resolution_reason'] : null,
                'resolution_note'     => $modelData['resolution_note'] ?? null,
                'resolved_minutes'    => $day->clocked_minutes ?? 0,
                'resolved_by_user_id' => $user->id,
                'resolved_at'         => now(),
            ]);
            $day->is_warning_open = $day->needsAction();
            $day->save();
        });

        return $day;
    }

    public function rules(): array
    {
        return [
            'resolution'        => ['required', Rule::enum(ManufactureArtisanDayResolutionEnum::class)],
            'resolution_reason' => ['required_if:resolution,'.ManufactureArtisanDayResolutionEnum::REASON_GIVEN->value, 'nullable', Rule::enum(ManufactureArtisanDayShortReasonEnum::class)],
            'resolution_note'   => [
                'required_if:resolution,'.ManufactureArtisanDayResolutionEnum::NOT_APPROVED->value,
                'required_if:resolution_reason,'.ManufactureArtisanDayShortReasonEnum::OTHER->value,
                'nullable',
                'string',
                'max:1000',
            ],
            'lock_reason'       => ['sometimes', 'nullable', 'string', 'max:1000'],
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
            "accounting.{$this->organisation->id}.edit",
        ]);
    }

    public function action(ManufactureArtisanDay $day, User $user, array $modelData): ManufactureArtisanDay
    {
        $this->asAction = true;
        $this->initialisationFromProduction($day->production, $modelData);

        return $this->handle($day, $user, $this->validatedData);
    }

    public function asController(ManufactureArtisanDay $manufactureArtisanDay, ActionRequest $request): ManufactureArtisanDay
    {
        $this->initialisationFromProduction($manufactureArtisanDay->production, $request);

        return $this->handle($manufactureArtisanDay, $request->user(), $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
