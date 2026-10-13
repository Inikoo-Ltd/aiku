<?php

namespace App\Actions\HumanResources\EmployeeContract;

use App\Actions\HumanResources\Leave\RecalculateEmployeeLeaveBalance;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\EmployeeLeaveBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class UpdateLeaveBalanceAdjustment extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public const float MAX_DAYS = 366.0;

    /**
     * @param  array{annual_adjustment?: float|int|string, medical_adjustment?: float|int|string, unpaid_adjustment?: float|int|string}  $modelData
     */
    public function handle(EmployeeLeaveBalance $balance, array $modelData): EmployeeLeaveBalance
    {
        $recalculator = RecalculateEmployeeLeaveBalance::make();

        foreach (RecalculateEmployeeLeaveBalance::ADJUSTMENT_COLUMNS as $adjustmentColumn) {
            if (array_key_exists($adjustmentColumn, $modelData)) {
                $balance->setAttribute($adjustmentColumn, (float) $modelData[$adjustmentColumn]);
            }
        }

        $recorded = $recalculator->recordedDays($balance) ?? array_fill_keys(array_keys(RecalculateEmployeeLeaveBalance::ADJUSTMENT_COLUMNS), 0.0);

        $balance->fill(RecalculateEmployeeLeaveBalance::withAdjustments($recorded, $balance));
        $balance->save();

        return $balance;
    }

    public function rules(): array
    {
        $adjustment = ['sometimes', 'required', 'numeric', 'min:0', 'max:'.self::MAX_DAYS, 'multiple_of:0.5'];

        return [
            'annual_adjustment'  => $adjustment,
            'medical_adjustment' => $adjustment,
            'unpaid_adjustment'  => $adjustment,
        ];
    }

    public function asController(EmployeeLeaveBalance $balance, ActionRequest $request): EmployeeLeaveBalance
    {
        $balance->load('employee.organisation.group');
        abort_unless($balance->employee?->organisation !== null, 404);
        $this->initialisation($balance->employee->organisation, $request);

        return $this->handle($balance, $this->validatedData);
    }

    public function action(EmployeeLeaveBalance $balance, array $modelData): EmployeeLeaveBalance
    {
        $this->asAction = true;
        $this->initialisation($balance->employee->organisation, $modelData);

        return $this->handle($balance, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Leave balance adjustment saved.'),
        ]);
    }
}
