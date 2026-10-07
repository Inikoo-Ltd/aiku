<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydrateOrgSupplierProducts;
use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydrateOrgSuppliers;
use App\Actions\Procurement\OrgAgent\StoreOrgAgent;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplierFromFreeSupplier;
use App\Actions\Procurement\OrgSupplierProducts\SyncOrgSupplierProducts;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateSupplierProducts;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateSuppliers;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgSuppliers;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\ShoppingListItem;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SetSupplierAgent
{
    use AsAction;

    /**
     * Under an agent, every organisation buying from the supplier follows it: those not trading with the agent yet get the org agent created.
     * Without an agent, the supplier goes back to the organisations that buy independent suppliers directly.
     *
     * @throws \Throwable
     */
    public function handle(Supplier $supplier, ?Agent $agent): Supplier
    {
        $previousAgent = $supplier->agent;

        if ($previousAgent?->id === $agent?->id) {
            return $supplier;
        }

        $touchedOrgAgents = DB::transaction(function () use ($supplier, $agent) {
            $touchedOrgAgents = collect();

            if ($agent) {
                $this->addAgentToOrganisationsBuyingFromSupplier($supplier, $agent);
            }

            $parents = $agent
                ? $agent->orgAgents()->get()->keyBy('organisation_id')
                : StoreOrgSupplierFromFreeSupplier::make()->getOrganisations($supplier)->keyBy('id');

            $supplier->update(['agent_id' => $agent?->id]);
            $supplier->supplierProducts()->update(['agent_id' => $agent?->id]);

            foreach ($supplier->orgSuppliers as $orgSupplier) {
                if ($orgSupplier->org_agent_id) {
                    $touchedOrgAgents->push($orgSupplier->orgAgent);
                }

                $parent   = $parents->get($orgSupplier->organisation_id);
                $orgAgent = $parent instanceof OrgAgent ? $parent : null;

                $orgSupplier->update([
                    'agent_id'     => $agent?->id,
                    'org_agent_id' => $orgAgent?->id,
                    'status'       => $parent ? $supplier->status : false,
                ]);
                $orgSupplier->orgSupplierProducts()->update(['org_agent_id' => $orgAgent?->id]);

                if ($orgAgent) {
                    $touchedOrgAgents->push($orgAgent);
                }
            }

            $organisationsWithOrgSupplier = $supplier->orgSuppliers()->pluck('organisation_id')->all();

            foreach ($parents as $organisationId => $parent) {
                if (in_array($organisationId, $organisationsWithOrgSupplier)) {
                    continue;
                }

                $orgSupplier = StoreOrgSupplier::make()->action($parent, $supplier);
                SyncOrgSupplierProducts::run($orgSupplier);

                if ($parent instanceof OrgAgent) {
                    $touchedOrgAgents->push($parent);
                }
            }

            ShoppingListItem::query()
                ->whereIn('state', [ShoppingListItemStateEnum::OPEN, ShoppingListItemStateEnum::DISMISS_PROPOSED])
                ->where('supplier_id', $supplier->id)
                ->update(['agent_id' => $agent?->id]);

            return $touchedOrgAgents;
        });

        foreach (array_filter([$previousAgent, $agent]) as $affectedAgent) {
            AgentHydrateSuppliers::dispatch($affectedAgent);
            AgentHydrateSupplierProducts::dispatch($affectedAgent);
        }

        foreach ($touchedOrgAgents->unique('id') as $orgAgent) {
            OrgAgentHydrateOrgSuppliers::dispatch($orgAgent);
            OrgAgentHydrateOrgSupplierProducts::dispatch($orgAgent);
        }

        foreach ($supplier->orgSuppliers()->with('organisation')->get() as $orgSupplier) {
            OrganisationHydrateOrgSuppliers::dispatch($orgSupplier->organisation);
        }

        return $supplier->refresh();
    }

    private function addAgentToOrganisationsBuyingFromSupplier(Supplier $supplier, Agent $agent): void
    {
        $organisations = $supplier->orgSuppliers()
            ->where('status', true)
            ->whereNotIn('organisation_id', $agent->orgAgents()->pluck('organisation_id'))
            ->with('organisation')
            ->get()
            ->pluck('organisation');

        foreach ($organisations as $organisation) {
            StoreOrgAgent::make()->action($organisation, $agent, []);
        }
    }
}
