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
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterVariant extends OrgAction
{
    use WithActionUpdate;

    protected MasterVariant $masterVariant;

    public function handle(MasterVariant $masterVariant, array $modelData): MasterVariant
    {
        $masterVariant->update($modelData);

        // TODO Hydrate Child

        return $masterVariant;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->number_minions = array_reduce(data_get($this->variant['variants'], '*.options'), function ($carry, $item) {
            return $carry * count($item);
        }, 1);

        $this->number_dimensions = count($this->variant['variants']);
        $this->number_used_slots = count($this->variant['products']);
        $this->number_used_slots_for_sale = MasterAsset::whereIn('id', array_keys($this->variant['products']))->select('is_for_sale', true)->count();

        $this->data = $request->input('variant');
        $this->leader_id = data_get(collect($request->input('variant.products'))->where('is_leader', true)->first(), 'product.id');
    }

    public function rules(): array
    {
        return [
            'leader_id'                     => ['required', 'exists:master_assets,id'],
            'number_minions'                => ['sometimes', 'numeric'], // It's calculated in prepareForValidation, I'm using sometimes to ignore errorbag
            'number_dimensions'             =>  ['sometimes', 'numeric'], // It's calculated in prepareForValidation, I'm using sometimes to ignore errorbag
            'number_used_slots'             => ['sometimes', 'numeric'], // It's calculated in prepareForValidation, I'm using sometimes to ignore errorbag
            'number_used_slots_for_sale'    => ['sometimes', 'numeric'], // It's calculated in prepareForValidation, I'm using sometimes to ignore errorbag
            'data'                          => ['sometimes', 'array'],
            'data.variants'                 =>  ['sometimes', 'array'],
            'data.groupBy'                  => ['sometimes', 'string'],
            'data.products'                 => ['sometimes', 'array', 'min:1'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'leader_id.required'    => __('A leader product must be selected'),
            'data.groupBy'          => __('A grouping criteria must be selected'),
            'data.products.min'     => __('At least one product must be present in the variant'),
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
