<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 14:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureBreak;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\User\GetUserCurrentEmployee;
use App\Models\Production\ManufactureBreak;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StartManufactureBreak extends OrgAction
{
    public const ALLOWED_MINUTES = [5, 15, 30];

    public function handle(User $user, Production $production, array $modelData): ManufactureBreak
    {
        return DB::transaction(function () use ($user, $production, $modelData) {
            $alreadyOnBreak = ManufactureBreak::where('user_id', $user->id)->open()->lockForUpdate()->exists();
            if ($alreadyOnBreak) {
                throw ValidationException::withMessages([
                    'break' => __('You are already on a break'),
                ]);
            }

            return ManufactureBreak::create([
                'group_id'        => $production->group_id,
                'organisation_id' => $production->organisation_id,
                'production_id'   => $production->id,
                'user_id'         => $user->id,
                'employee_id'     => GetUserCurrentEmployee::run($user, $production->organisation_id)?->id,
                'planned_minutes' => $modelData['planned_minutes'],
                'started_at'      => now(),
            ]);
        });
    }

    public function rules(): array
    {
        return [
            'planned_minutes' => ['required', 'integer', Rule::in(self::ALLOWED_MINUTES)],
        ];
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
        ]);
    }

    public function action(User $user, Production $production, array $modelData): ManufactureBreak
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($user, $production, $this->validatedData);
    }

    public function asController(Production $production, ActionRequest $request): ManufactureBreak
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($request->user(), $production, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
