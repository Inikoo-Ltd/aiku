<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Dec 2025 11:28:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterVariant;

use App\Actions\Catalogue\Variant\StoreVariantFromMaster;
use App\Actions\OrgAction;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterVariant;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreMasterVariant extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $masterProductCategory, array $modelData): MasterVariant
    {
        data_set($modelData, 'master_family_id', $masterProductCategory->id);
        data_set($modelData, 'master_sub_department_id', $masterProductCategory->master_sub_department_id);
        data_set($modelData, 'master_department_id', $masterProductCategory->master_department_id);
        data_set($modelData, 'group_id', $masterProductCategory->group_id);
        data_set($modelData, 'master_shop_id', $masterProductCategory->master_shop_id);

        /** @var MasterVariant $masterVariant */
        $masterVariant = DB::transaction(function () use ($modelData) {
            $masterVariant = MasterVariant::create($modelData);

            // Initialize aggregates/relations
            $masterVariant->stats()->create();
            $masterVariant->salesIntervals()->create();
            $masterVariant->orderingStats()->create();
            $masterVariant->orderingIntervals()->create();
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $masterVariant->timeSeries()->create(['frequency' => $frequency]);
            }

            $masterVariant->refresh();

            foreach($masterVariant->masterFamily->productCategories as $productCategory){
                if(!$productCategory->shop) continue;
                StoreVariantFromMaster::make()->action(
                    masterVariant: $masterVariant,
                    shop: $productCategory->shop,
                    modelData: Arr::except($modelData, [
                        'master_family_id',
                        'master_sub_department_id',
                        'master_department_id',
                        'group_id',
                        'master_shop_id'
                    ])
                );
            }

            return $masterVariant;
        });

        // TODO Hydrate Child

        return $masterVariant;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$request->input('data_variants.products') || !collect($request->input('data_variants.products'))->where('is_leader', true)->count() > 0) {
            throw ValidationException::withMessages([
                'leader_id' => __('A leader product must first be selected before being able to generate this variant.')
            ]);
        }

        $this->leader_id = data_get(collect($request->input('data_variants.products'))->where('is_leader', true)->first(), 'product.id');

        $code = MasterAsset::find($this->leader_id)->code . '-var-' . now()->format('His');
        $this->set('code', $code);
        
        $this->number_minions = array_reduce(data_get($this->data_variants['variants'], '*.options'), function ($carry, $item) {
            return $carry * count($item);
        }, 1) - 1; // Minus one to exclude the leader product
        $this->number_dimensions = count($this->data_variants['variants']);
        $this->number_used_slots = count($this->data_variants['products']);
        $this->number_used_slots_for_sale = MasterAsset::whereIn('id', array_keys($this->data_variants['products']))->select('is_for_sale', true)->count();

        if ($this->data_variants) {
            $this->set('data', $this->data_variants);
        }
    }

    public function rules(): array
    {
        return [
            'leader_id'                     =>  ['required', 'exists:master_assets,id'],
            'code'                          =>  [
                                                    'required',
                                                    'max:32',
                                                    new AlphaDashDot(),
                                                    new IUnique(
                                                        table: 'master_variants',
                                                        extraConditions: [
                                                            ['column' => 'master_shop_id', 'value' => $this->masterShop->id ?? null],
                                                            ['column' => 'deleted_at', 'operator' => 'null'],
                                                        ]
                                                    ),
                                                ],
            'number_minions'                =>  ['sometimes', 'numeric'], // It's calculated in prepareForValidation, I'm using sometimes to ignore errorbag
            'number_dimensions'             =>  ['sometimes', 'numeric'], // It's calculated in prepareForValidation, I'm using sometimes to ignore errorbag
            'number_used_slots'             =>  ['sometimes', 'numeric'], // It's calculated in prepareForValidation, I'm using sometimes to ignore errorbag
            'number_used_slots_for_sale'    =>  ['sometimes', 'numeric'], // It's calculated in prepareForValidation, I'm using sometimes to ignore errorbag
            'data'                          =>  ['required', 'array'],
            'data.variants'                 =>  ['sometimes', 'array'],
            'data.groupBy'                  =>  ['sometimes', 'string'],
            'data.products'                 =>  ['sometimes', 'array', 'min:1'],
        ];
    }

    public function getValidationMessages(): array
    {
        $validationMessages = [
            'data.groupBy'          => __('A grouping criteria must be selected'),
            'data.products'     => __('At least one product must be present in the variant'),
        ];

        return $validationMessages;
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterProductCategory $masterProductCategory, array $modelData, int $hydratorsDelay = 0): MasterVariant
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($masterProductCategory->group, $modelData);

        return $this->handle($masterProductCategory, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        $masterVariant = $this->handle($masterProductCategory, $this->validatedData);

        return $this->redirectSuccess($masterVariant, $request);
    }

    public function redirectSuccess(MasterVariant $masterVariant, ActionRequest $request): RedirectResponse
    {
        return redirect()
            ->route('grp.masters.master_shops.show.master_families.show', [
                'tab'          => 'variants',
                'masterShop'    => $masterVariant->masterShop->slug,
                'masterFamily'  => $masterVariant->masterFamily->slug,
            ])
            ->with(
                'notification',
                [
                    'status'  => 'success',
                    'title'   => __('Success!'),
                    'description' => __('Master Variant :_masterVarCode has been created successfully.', ['_masterVarCode' => $masterVariant->code]),
                ]
            )
            ->setStatusCode(303);
    }
}
