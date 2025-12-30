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
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\Variant;
use App\Models\Masters\MasterVariant;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreVariantFromMaster extends OrgAction
{
    use WithVariantDataPreparation;

    protected MasterVariant $parent;
    protected Shop $shop;

    /**
     * @throws \Throwable
     */
    public function handle(array $modelData): Variant
    {
        /** @var Variant $variant */
        $variant = DB::transaction(function () use ($modelData) {
            $variant = Variant::create($modelData);

            // TODO Stats. Need Raul to create the Model for each
            // $variant->orderingStats()->create();
            // $variant->orderingIntervals()->create();
            // foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            //     $variant->timeSeries()->create(['frequency' => $frequency]);
            // }

            $variant->refresh();

            return $variant;
        });

        return $variant;
    }

    public function prepareForValidation(): void
    {
        // Call function set on Trait. If need to update logic, update it there please.
        $this->prepareVariantData();
    }

    public function rules(): array
    {
        return [
            'leader_id'                     =>  ['required', 'exists:products,id'],
            'family_id'                     =>  ['required', Rule::exists('product_categories', 'id')->where('type', ProductCategoryTypeEnum::FAMILY)],
            'department_id'                 =>  ['nullable', Rule::exists('product_categories', 'id')->where('type', ProductCategoryTypeEnum::DEPARTMENT)],
            'sub_department_id'             =>  ['nullable', Rule::exists('product_categories', 'id')->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)],
            'shop_id'                       =>  ['required', Rule::exists('shops', 'id')],
            'organisation_id'               =>  ['required', Rule::exists('organisations', 'id')],
            'group_id'                      =>  ['required', Rule::exists('groups', 'id')],
            'code'                          =>  [
                                                    'required',
                                                    'max:32',
                                                    new AlphaDashDot(),
                                                    new IUnique(
                                                        table: 'variants',
                                                        extraConditions: [
                                                            ['column' => 'master_shop_id', 'value' => $this->masterShop->id ?? null],
                                                            ['column' => 'deleted_at', 'operator' => 'null'],
                                                        ]
                                                    ),
                                                ],
            'slug'                          =>  ['required', 'string'],
            'number_minions'                =>  ['required', 'numeric'],
            'number_dimensions'             =>  ['required', 'numeric'],
            'number_used_slots'             =>  ['required', 'numeric'],
            'number_used_slots_for_sale'    =>  ['required', 'numeric'],
            'data'                          =>  ['required', 'array'],
            'data.variants'                 =>  ['required', 'array'],
            'data.groupBy'                  =>  ['required', 'string'],
            'data.products'                 =>  ['required', 'array', 'min:1'],
            'master_variant_id'             =>  ['required'],
        ];
    }
    
    /**
     * @throws \Throwable
     */
    public function action(MasterVariant $masterVariant, Shop $shop, array $modelData, int $hydratorsDelay = 0): Variant
    {
        $this->parent = $masterVariant;
        $this->shop = $shop;

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($masterVariant->group, $modelData);

        return $this->handle($this->validatedData);
    }
}
