<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydrateOrgSupplierProducts;
use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydrateOrgSuppliers;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
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

class MoveSupplierToAgent
{
    use AsAction;

    /**
     * Organisations trading with the new agent keep ordering this supplier through their org agent;
     * the others lose it, because an agent's supplier can only be bought through that agent.
     *
     * @throws \Throwable
     */
    public function handle(Supplier $supplier, Agent $agent): Supplier
    {
        $previousAgent = $supplier->agent;

        if ($previousAgent?->id === $agent->id) {
            return $supplier;
        }

        $orgAgents = $agent->orgAgents()->get()->keyBy('organisation_id');

        $touchedOrgAgents = DB::transaction(function () use ($supplier, $agent, $orgAgents) {
            $touchedOrgAgents = collect();

            $supplier->update(['agent_id' => $agent->id]);
            $supplier->supplierProducts()->update(['agent_id' => $agent->id]);

            foreach ($supplier->orgSuppliers as $orgSupplier) {
                if ($orgSupplier->org_agent_id) {
                    $touchedOrgAgents->push($orgSupplier->orgAgent);
                }

                /** @var OrgAgent|null $orgAgent */
                $orgAgent = $orgAgents->get($orgSupplier->organisation_id);

                $orgSupplier->update([
                    'agent_id'     => $agent->id,
                    'org_agent_id' => $orgAgent?->id,
                    'status'       => $orgAgent ? $supplier->status : false,
                ]);
                $orgSupplier->orgSupplierProducts()->update(['org_agent_id' => $orgAgent?->id]);

                if ($orgAgent) {
                    $touchedOrgAgents->push($orgAgent);
                }
            }

            $organisationsWithOrgSupplier = $supplier->orgSuppliers()->pluck('organisation_id')->all();

            foreach ($orgAgents->reject(fn (OrgAgent $orgAgent) => in_array($orgAgent->organisation_id, $organisationsWithOrgSupplier)) as $orgAgent) {
                $orgSupplier = StoreOrgSupplier::make()->action($orgAgent, $supplier);
                SyncOrgSupplierProducts::run($orgSupplier);
                $touchedOrgAgents->push($orgAgent);
            }

            ShoppingListItem::query()
                ->whereIn('state', [ShoppingListItemStateEnum::OPEN, ShoppingListItemStateEnum::DISMISS_PROPOSED])
                ->where('supplier_id', $supplier->id)
                ->update(['agent_id' => $agent->id]);

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
}
