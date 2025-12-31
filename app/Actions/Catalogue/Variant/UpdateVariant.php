<?php

/*
 * author Louis Perez
 * created on 30-12-2025-14h-05m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Catalogue\Variant;

use App\Actions\OrgAction;
use App\Actions\Catalogue\Variant\Traits\WithVariantDataPreparation;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\Variant;
use App\Models\Masters\MasterVariant;

class UpdateVariant extends OrgAction
{
    use WithVariantDataPreparation;
    
    protected MasterVariant $parent;
    protected Shop $shop;

    /**
     * @throws \Throwable
     */
    public function handle(Variant $variant, array $modelData): Variant
    {
        $variant->update($modelData);
        $variant->refresh();

        return $variant;
    }

    public function prepareForValidation(): void
    {
        $this->prepareForVariantUpdate();
    }

    public function rules(): array
    {
        return [
            'leader_id'                     =>  ['required', 'exists:products,id'],
            'number_minions'                =>  ['required', 'numeric'],
            'number_dimensions'             =>  ['required', 'numeric'],
            'number_used_slots'             =>  ['required', 'numeric'],
            'number_used_slots_for_sale'    =>  ['required', 'numeric'],
            'data'                          =>  ['required', 'array'],
            'data.variants'                 =>  ['required', 'array'],
            'data.groupBy'                  =>  ['required', 'string'],
            'data.products'                 =>  ['required', 'array', 'min:1'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'leader_id.required'    => __('A leader product must be selected'),
            'data.groupBy'          => __('A grouping criteria must be selected'),
            'data.products'         => __('At least one product must be present in the variant'),
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Variant $variant, array $modelData, int $hydratorsDelay = 0): Variant
    {
        $this->parent = $variant->masterVariant;
        $this->shop = $variant->shop;

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($variant->group, $modelData);

        return $this->handle($variant, $this->validatedData);
    }
}
