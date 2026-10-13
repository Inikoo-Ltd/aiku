<?php

namespace App\Actions\HumanResources\EmployeeContract;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\EmployeeContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class PreviewContractLeaveBalances extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public const string STORE = 'store';

    public const string UPDATE = 'update';

    public const string DELETE = 'delete';

    private string $operation = self::STORE;

    /**
     * @param  array<string, mixed>  $data
     * @return array{requires_confirmation: bool, entries: array<int, array<string, mixed>>}
     */
    public function handle(Employee|EmployeeContract $subject, string $operation, array $data): array
    {
        $planner = PlanContractLeaveBalances::make();
        $choices = $data['balance_choices'] ?? null;

        $plan = match (true) {
            $subject instanceof Employee  => $planner->forStore($subject, $data, $choices),
            $operation === self::DELETE   => $planner->forDelete($subject, $choices),
            default                       => $planner->forUpdate($subject, $data, $choices),
        };

        return $planner->preview($plan);
    }

    public function rules(): array
    {
        $contractRules = match ($this->operation) {
            self::STORE  => StoreEmployeeContract::make()->rules(),
            self::UPDATE => UpdateEmployeeContract::make()->rules(),
            default      => DeleteEmployeeContract::make()->rules(),
        };

        return [
            ...$contractRules,
            'operation' => ['sometimes', Rule::in([self::UPDATE, self::DELETE])],
        ];
    }

    public function inEmployee(Employee $employee, ActionRequest $request): JsonResponse
    {
        $employee->load('organisation.group');
        $this->operation = self::STORE;
        $this->initialisation($employee->organisation, $request);

        return new JsonResponse($this->handle($employee, self::STORE, $this->validatedData));
    }

    public function inContract(EmployeeContract $contract, ActionRequest $request): JsonResponse
    {
        $contract->load('employee.organisation.group');
        $this->operation = $request->input('operation') === self::DELETE ? self::DELETE : self::UPDATE;
        $this->initialisation($contract->employee->organisation, $request);

        return new JsonResponse($this->handle($contract, $this->operation, $this->validatedData));
    }
}
