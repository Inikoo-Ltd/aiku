<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Aug 2024 12:03:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockFamily;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgStockFamilies;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Inventory\OrgStockFamily\OrgStockFamilyStateEnum;
use App\Http\Resources\Inventory\OrgStockFamiliesResource;
use App\Models\Inventory\OrgStockFamily;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrgStockFamily extends OrgAction
{
    use WithActionUpdate;

    private OrgStockFamily $orgStockFamily;


    public function handle(OrgStockFamily $orgStockFamily, array $modelData): OrgStockFamily
    {
        $orgStockFamily = $this->update($orgStockFamily, $modelData, ['data']);

        if (Arr::hasAny($orgStockFamily->getChanges(), ['state'])) {
            OrganisationHydrateOrgStockFamilies::run($orgStockFamily->organisation);
        }

        return $orgStockFamily;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("inventory.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        return [
            'code'  => [
                'sometimes',
                'required',
                'string',
                'max:255',
              ],
            'name'  => ['sometimes', 'required', 'string', 'max:255'],
            'state' => ['sometimes', 'required', Rule::enum(OrgStockFamilyStateEnum::class)],
        ];
    }

    public function action(OrgStockFamily $orgStockFamily, array $modelData): OrgStockFamily
    {
        $this->asAction       = true;
        $this->orgStockFamily = $orgStockFamily;
        $this->initialisation($orgStockFamily->organisation, $modelData);

        return $this->handle($orgStockFamily, $this->validatedData);
    }

    public function asController(Organisation $organisation, OrgStockFamily $orgStockFamily, ActionRequest $request): OrgStockFamily
    {
        $this->orgStockFamily = $orgStockFamily;
        $this->initialisation($organisation, $request);

        return $this->handle($orgStockFamily, $this->validatedData);
    }

    public function jsonResponse(OrgStockFamily $orgStockFamily): OrgStockFamiliesResource
    {
        return new OrgStockFamiliesResource($orgStockFamily);
    }
}
