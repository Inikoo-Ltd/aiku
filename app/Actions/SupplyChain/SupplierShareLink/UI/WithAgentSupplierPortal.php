<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-16 15:05
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\OrgSupplier\WithOrgSupplierSubNavigation;
use App\Actions\SupplyChain\Supplier\UI\ShowSupplier;
use App\Actions\SupplyChain\Supplier\WithSupplierSubNavigation;
use App\Actions\Traits\Authorisations\SupplierPortalPermissions;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

/**
 * The supplier portal pages are served under supply chain and, for the agent a supplier is bought through, under the agent's own supplier page, with that page's navigation.
 */
trait WithAgentSupplierPortal
{
    use WithSupplierSubNavigation;
    use WithOrgSupplierSubNavigation;

    protected ?OrgSupplier $agentOrgSupplier = null;

    protected function initialisationInAgentOrgSupplier(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): static
    {
        abort_unless(SupplierPortalPermissions::agentOrganisationBuysThrough($organisation, $orgSupplier->supplier), 404);
        $this->agentOrgSupplier = $orgSupplier;

        return $this->initialisation($organisation, $request);
    }

    public function withAgentOrgSupplier(?Organisation $organisation, ?OrgSupplier $orgSupplier): static
    {
        if ($organisation && $orgSupplier) {
            $this->organisation     = $organisation;
            $this->agentOrgSupplier = $orgSupplier;
        }

        return $this;
    }

    protected function supplierPortalBreadcrumbs(Supplier $supplier, string $suffix): array
    {
        if ($this->agentOrgSupplier) {
            return ShowOrgSupplier::make()->getBreadcrumbs(
                'grp.org.procurement.org_suppliers.show',
                ['organisation' => $this->organisation->slug, 'supplier' => $this->agentOrgSupplier->supplier->slug],
                $suffix
            );
        }

        return ShowSupplier::make()->getBreadcrumbs($supplier, 'grp.supply-chain.suppliers.show', [], $suffix);
    }

    protected function supplierPortalNavigation(Supplier $supplier): array
    {
        return $this->agentOrgSupplier ? $this->getOrgSupplierNavigation($this->agentOrgSupplier) : $this->getSupplierNavigation($supplier);
    }
}
