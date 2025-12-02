<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 May 2024 14:02:40 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier;

use App\Actions\Catalogue\ProductCategory\UpdateSubDepartmentDepartment;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydrateOrgSuppliers;
use App\Actions\Procurement\OrgSupplier\Search\OrgSupplierRecordSearch;
use App\Actions\SupplyChain\Supplier\UpdateSupplier;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgSuppliers;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Procurement\OrgSupplier;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrgSupplier extends OrgAction
{
    use WithActionUpdate;


    public function handle(OrgSupplier $orgSupplier, $modelData = []): OrgSupplier
    {
        $supplierData = Arr::only($modelData, ['company_name', 'contact_name']);
        Arr::forget($modelData, ['company_name', 'contact_name']);

        UpdateSupplier::run($orgSupplier->supplier, $supplierData);

        $orgSupplier = $this->update($orgSupplier, $modelData);

        OrgSupplierRecordSearch::dispatch($orgSupplier);
        if ($orgSupplier->wasChanged('status')) {
            OrganisationHydrateOrgSuppliers::dispatch($orgSupplier->organisation);
            if ($orgSupplier->org_agent_id) {
                OrgAgentHydrateOrgSuppliers::dispatch($orgSupplier->orgAgent);
            }
        }


        return $orgSupplier;
    }


    public function rules(ActionRequest $request): array
    {
        return [
            'source_id'    => 'sometimes|nullable|string|max:64',
            'status'       => ['sometimes', 'required', 'boolean'],
            'company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }

    public function action(OrgSupplier $orgSupplier, $modelData, $hydratorsDelay = 0): OrgSupplier
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($orgSupplier->organisation, $modelData);

        return $this->handle($orgSupplier, $this->validatedData);
    }

    public function asController(OrgSupplier $orgSupplier, ActionRequest $request): OrgSupplier
    {
        $this->initialisation($orgSupplier->organisation, $request);

        return $this->handle($orgSupplier, $this->validatedData);
    }


}
