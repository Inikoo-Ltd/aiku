<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 Oct 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artisan;

use App\Actions\OrgAction;
use App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay;
use App\Actions\Production\ManufactureTaskSession\CloseManufactureTaskSession;
use App\Enums\Production\Artisan\ArtisanPayTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SetArtisanPayType extends OrgAction
{
    /**
     * Sessions closed this week follow the new pay type too, so the week being paid is consistent.
     */
    public function handle(Production $production, Employee $employee, array $modelData): Employee
    {
        $payType = ArtisanPayTypeEnum::from($modelData['artisan_pay_type']);

        return DB::transaction(function () use ($production, $employee, $payType) {
            $employee->update(['artisan_pay_type' => $payType]);

            $closeAction = CloseManufactureTaskSession::make();
            ManufactureTaskSession::where('production_id', $production->id)
                ->where('employee_id', $employee->id)
                ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
                ->where('ended_at', '>=', self::effectiveFrom())
                ->lockForUpdate()
                ->get()
                ->each(function (ManufactureTaskSession $session) use ($closeAction, $payType) {
                    if ($session->isNonProductive()) {
                        $session->artisan_pay_type = $payType;
                    } else {
                        $closeAction->applyPayTerms($session, $payType);
                    }
                    $session->save();
                    CalculateManufactureTaskSessionPay::run($session);
                });

            return $employee;
        });
    }

    public static function effectiveFrom(?Carbon $changedAt = null): Carbon
    {
        return ($changedAt ?? now())->copy()->startOfWeek();
    }

    public function rules(): array
    {
        return [
            'artisan_pay_type' => ['required', Rule::enum(ArtisanPayTypeEnum::class)],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if (!$this->production->paysBySpeed()) {
            return false;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    public function action(Production $production, Employee $employee, array $modelData): Employee
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($production, $employee, $this->validatedData);
    }

    public function asController(Organisation $organisation, Production $production, Employee $employee, ActionRequest $request): Employee
    {
        abort_if($employee->organisation_id != $production->organisation_id, 404);
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $employee, $this->validatedData);
    }
}
