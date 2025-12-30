<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Dec 2025 11:32:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterVariant;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Masters\MasterVariant;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterVariant extends OrgAction
{
    use WithActionUpdate;

    protected MasterVariant $masterVariant;

    public function handle(MasterVariant $masterVariant, array $modelData): MasterVariant
    {
        return $this->update($masterVariant, $modelData, ['data']);
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->data = $request->input('variant');
        $this->leader_id = data_get(collect($request->input('variant.products'))->where('is_leader', true)->first(), 'product.id');
    }

    public function rules(): array
    {
        return [
            'leader_id' => ['required', 'exists:master_assets,id',],
            'data' => ['sometimes', 'array'],
        ];
    }

    public function action(MasterVariant $masterVariant, array $modelData, int $hydratorsDelay = 0): MasterVariant
    {
        $this->masterVariant   = $masterVariant;
        $this->asAction        = true;
        $this->hydratorsDelay  = $hydratorsDelay;
        $this->initialisationFromGroup($masterVariant->group, $modelData);

        return $this->handle($masterVariant, $this->validatedData);
    }

    public function asController(MasterVariant $masterVariant, ActionRequest $request): MasterVariant
    {
        $this->masterVariant = $masterVariant;
        $this->initialisationFromGroup($masterVariant->group, $request);

        return $this->handle($masterVariant, $this->validatedData);
    }
}
