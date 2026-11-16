<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankPayee\Json;

use App\Actions\Accounting\Traits\AuthorizesAccountingEdit;
use App\Actions\OrgAction;
use App\Models\HumanResources\Employee;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

/**
 * The suppliers, agents and companies of the group a payee of the banks can be linked to, and the employees of the
 * organisations whose accounting the person may edit.
 */
class GetBankPayeeLinkOptions extends OrgAction
{
    use AuthorizesAccountingEdit;

    /**
     * @param array<int, int> $employeeOrganisationIds
     * @return array<int, array{type: string, id: int, label: string}>
     */
    public function handle(Organisation $organisation, array $employeeOrganisationIds): array
    {
        $options = fn (string $type, string $model, string $column) => $model::where('group_id', $organisation->group_id)
            ->when($type === 'Employee', fn ($query) => $query->whereIn('organisation_id', $employeeOrganisationIds))
            ->whereNotNull($column)
            ->orderBy($column)
            ->get(['id', $column])
            ->map(fn ($row) => ['type' => $type, 'id' => $row->id, 'label' => $row->{$column}]);

        return collect()
            ->concat($options('Supplier', Supplier::class, 'name'))
            ->concat($options('Agent', Agent::class, 'name'))
            ->concat($options('Organisation', Organisation::class, 'name'))
            ->concat($options('Employee', Employee::class, 'contact_name'))
            ->values()
            ->all();
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        $user = $request->user();

        return $this->handle(
            $organisation,
            Organisation::where('group_id', $organisation->group_id)->pluck('id')->filter(fn (int $id) => $user->authTo("accounting.$id.edit"))->values()->all()
        );
    }
}
