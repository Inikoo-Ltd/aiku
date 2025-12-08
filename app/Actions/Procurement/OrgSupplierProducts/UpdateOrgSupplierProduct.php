<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 16:05:57 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydrateOrgSupplierProducts;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydrateOrgSupplierProducts;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgSupplierProducts;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Models\Procurement\OrgSupplierProduct;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrgSupplierProduct extends OrgAction
{
    use WithActionUpdate;

    public function handle(OrgSupplierProduct $orgSupplierProduct, $modelData = []): OrgSupplierProduct
    {
        $orgSupplierProduct = $this->update($orgSupplierProduct, $modelData);

        if ($orgSupplierProduct->wasChanged(['status', 'is_available'])) {
            OrganisationHydrateOrgSupplierProducts::dispatch($orgSupplierProduct->organisation);
            OrgSupplierHydrateOrgSupplierProducts::dispatch($orgSupplierProduct->orgSupplier);
            if ($orgSupplierProduct->org_agent_id) {
                OrgAgentHydrateOrgSupplierProducts::dispatch($orgSupplierProduct->orgAgent);
            }
        }

        return $orgSupplierProduct;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction = true) {
            return true;
        }

        return $request->user()->authTo('procurement.'.$this->organisation->id.'.edit');
    }

    public function rules(ActionRequest $request): array
    {
        return [
            'source_id' => 'sometimes|nullable|string|max:64',
            'status' => ['sometimes', 'required', 'boolean'],
            'state' => ['sometimes', 'required', Rule::enum(OrgSupplierProductStateEnum::class)],
            'is_available' => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function action(OrgSupplierProduct $orgSupplierProduct, $modelData, $hydratorsDelay = 0): OrgSupplierProduct
    {
        $this->asAction = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($orgSupplierProduct->organisation, $modelData);

        return $this->handle($orgSupplierProduct, $this->validatedData);
    }
}
