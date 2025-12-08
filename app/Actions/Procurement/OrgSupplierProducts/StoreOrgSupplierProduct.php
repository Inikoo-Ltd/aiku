<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 20:51:57 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydrateOrgSupplierProducts;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydrateOrgSupplierProducts;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgSupplierProducts;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\SupplierProduct;
use Lorisleiva\Actions\ActionRequest;

class StoreOrgSupplierProduct extends OrgAction
{
    public function handle(OrgSupplier $orgSupplier, SupplierProduct $supplierProduct, $modelData = []): OrgSupplierProduct
    {
        data_set($modelData, 'group_id', $orgSupplier->group_id);
        data_set($modelData, 'organisation_id', $orgSupplier->organisation_id);

        data_set($modelData, 'org_agent_id', $orgSupplier->org_agent_id);
        data_set($modelData, 'org_supplier_id', $orgSupplier->id);

        $state = match ($supplierProduct->state) {
            SupplierProductStateEnum::DISCONTINUING => OrgSupplierProductStateEnum::DISCONTINUING,
            SupplierProductStateEnum::DISCONTINUED => OrgSupplierProductStateEnum::DISCONTINUED,
            default => OrgSupplierProductStateEnum::ACTIVE
        };
        data_set($modelData, 'state', $state);
        data_set($modelData, 'is_available', $supplierProduct->is_available);

        /** @var OrgSupplierProduct $orgSupplierProduct */
        $orgSupplierProduct = $supplierProduct->orgSupplierProducts()->create($modelData);
        $orgSupplierProduct->stats()->create();

        OrganisationHydrateOrgSupplierProducts::dispatch($orgSupplier->organisation);
        OrgSupplierHydrateOrgSupplierProducts::dispatch($orgSupplier);
        if ($orgSupplierProduct->org_agent_id) {
            OrgAgentHydrateOrgSupplierProducts::dispatch($orgSupplierProduct->orgAgent);
        }

        return $orgSupplierProduct;
    }

    public function rules(ActionRequest $request): array
    {
        return [
            'source_id' => 'sometimes|nullable|string|max:64',
        ];
    }

    public function action(OrgSupplier $orgSupplier, SupplierProduct $supplierProduct, $modelData = [], $hydratorsDelay = 0): OrgSupplierProduct
    {
        $this->asAction = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($orgSupplier->organisation, $modelData);

        return $this->handle($orgSupplier, $supplierProduct, $this->validatedData);
    }
}
