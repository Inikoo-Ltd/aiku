<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 11:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureArtisanDay;

use App\Actions\OrgAction;
use App\Models\Production\ManufacturePaidTimeRule;
use App\Models\Production\ManufacturePayrollAdjustment;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The Production Manager or the accountant sets which clocked time is paid from a day on. It changes pay, so the day cannot
 * be in a week already locked for payroll: paid weeks stay as paid.
 */
class SetManufacturePaidTimeRule extends OrgAction
{
    public function handle(Production $production, User $user, array $modelData): ManufacturePaidTimeRule
    {
        if ($modelData['effective_from'] < ManufacturePayrollAdjustment::lockedBefore($production)->toDateString()) {
            throw ValidationException::withMessages(['effective_from' => __('That week is locked for payroll: choose a day in a week not paid yet')]);
        }

        return ManufacturePaidTimeRule::updateOrCreate(
            [
                'production_id'  => $production->id,
                'effective_from' => $modelData['effective_from'],
            ],
            [
                'group_id'          => $production->group_id,
                'organisation_id'   => $production->organisation_id,
                'inside_shift_only' => $modelData['inside_shift_only'],
                'rounding_minutes'  => $modelData['rounding_minutes'],
                'set_by_user_id'    => $user->id,
            ]
        );
    }

    public function rules(): array
    {
        return [
            'inside_shift_only' => ['required', 'boolean'],
            'rounding_minutes'  => ['required', 'integer', 'min:1', 'max:60'],
            'effective_from'    => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::canSet($request->user(), $this->production);
    }

    public static function canSet(User $user, Production $production): bool
    {
        return $user->authTo([
            'org-supervisor.'.$production->organisation_id,
            "accounting.$production->organisation_id.edit",
        ]);
    }

    public function action(Production $production, User $user, array $modelData): ManufacturePaidTimeRule
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($production, $user, $this->validatedData);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): ManufacturePaidTimeRule
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $request->user(), $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
