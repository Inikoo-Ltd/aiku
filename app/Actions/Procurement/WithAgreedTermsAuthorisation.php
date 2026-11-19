<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 Oct 2026 02:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;

/**
 * Agreed terms and procurement notes hang from group records (supplier, supplier product, agent)
 * that every organisation shares, so the right to write them comes from any buying organisation
 * of the group. Agent organisations write none of them except notes on their own suppliers, visible to the
 * agent, so buyers and agent staff can tell each other about the supplier (GetAgreement::agentSupplierNoteForm).
 */
trait WithAgreedTermsAuthorisation
{
    protected function canWriteProcurementNotes(User $user, Group $group, ?int $organisationId = null): bool
    {
        if ($organisationId === null && $user->authTo('supply-chain.edit')) {
            return true;
        }

        return $this->hasProcurementPermission($user, $group, $organisationId, fn (int $id) => "procurement.$id.edit");
    }

    /**
     * Staff of the supplier's agent who may edit its procurement write notes on the supplier, for the agent
     * itself (no organisation) or for an organisation that buys from the supplier.
     */
    protected function canWriteAgentSupplierNotes(User $user, Supplier $supplier, ?int $organisationId = null): bool
    {
        $agentOrganisationId = $supplier->agent?->organisation_id;
        if (!$agentOrganisationId || !$user->authTo("procurement.$agentOrganisationId.edit")) {
            return false;
        }

        return $organisationId === null
            || OrgSupplier::where('supplier_id', $supplier->id)->where('organisation_id', $organisationId)->where('status', true)->exists();
    }

    /**
     * Goods in staff of any of the organisation's warehouses may report incidents on what arrives, but write no other note.
     */
    protected function canReportIncidents(User $user, ?int $organisationId): bool
    {
        if ($organisationId === null) {
            return false;
        }

        $permissions = Warehouse::where('organisation_id', $organisationId)->pluck('id')
            ->map(fn (int $warehouseId) => "incoming.$warehouseId.edit")
            ->all();

        return $permissions !== [] && $user->authTo($permissions);
    }

    protected function isProcurementSupervisor(User $user, Group $group, ?int $organisationId = null): bool
    {
        return $this->hasProcurementPermission($user, $group, $organisationId, fn (int $id) => "org-supervisor.$id.procurement");
    }

    private function hasProcurementPermission(User $user, Group $group, ?int $organisationId, callable $permission): bool
    {
        $permissions = $group->organisations()
            ->where('type', '!=', OrganisationTypeEnum::AGENT)
            ->when($organisationId, fn ($query) => $query->where('id', $organisationId))
            ->pluck('id')
            ->map($permission)
            ->all();

        return $permissions !== [] && $user->authTo($permissions);
    }
}
