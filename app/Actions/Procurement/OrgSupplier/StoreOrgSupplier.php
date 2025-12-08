<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Apr 2024 20:54:46 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydrateOrgSuppliers;
use App\Actions\Procurement\OrgSupplier\Search\OrgSupplierRecordSearch;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgSuppliers;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StoreOrgSupplier extends OrgAction
{
    use WithNoStrictRules;

    /**
     * @throws \Throwable
     */
    public function handle(Organisation|OrgAgent $parent, Supplier $supplier, $modelData = [])
    {
        if ($parent instanceof OrgAgent) {
            $organisation = $parent->organisation;
        } else {
            $organisation = $parent;
        }

        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $organisation->id);
        data_set($modelData, 'status', $supplier->status);
        data_set($modelData, 'supplier_id', $supplier->id);
        data_set($modelData, 'agent_id', $supplier->agent_id);

        $orgSupplier = DB::transaction(function () use ($parent, $modelData) {
            /** @var OrgSupplier $orgSupplier */
            $orgSupplier = $parent->orgSuppliers()->create($modelData);
            $orgSupplier->stats()->create();

            return $orgSupplier;
        });

        OrgSupplierRecordSearch::dispatch($orgSupplier)->delay($this->hydratorsDelay);
        OrganisationHydrateOrgSuppliers::dispatch($organisation)->delay($this->hydratorsDelay);
        if ($orgSupplier->org_agent_id) {
            OrgAgentHydrateOrgSuppliers::dispatch($orgSupplier->orgAgent)->delay($this->hydratorsDelay);
        }

        return $orgSupplier;
    }

    public function rules(ActionRequest $request): array
    {
        $rules = [];
        if (! $this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Organisation|OrgAgent $parent, Supplier $supplier, $modelData = [], $hydratorsDelay = 0, bool $strict = true): OrgSupplier
    {
        if ($parent instanceof OrgAgent) {
            $organisation = $parent->organisation;
        } else {
            $organisation = $parent;
        }

        $this->asAction = true;
        $this->strict = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($organisation, $modelData);

        return $this->handle($parent, $supplier, $this->validatedData);
    }
}
