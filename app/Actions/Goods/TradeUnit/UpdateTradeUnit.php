<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 May 2023 11:42:32 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateBarcodeFromTradeUnit;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateMarketingIngredientsFromTradeUnits;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateTradeUnitsFields;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateGrossWeightFromTradeUnits;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateMarketingDimensionFromTradeUnits;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateMarketingWeightFromTradeUnits;
use App\Actions\Goods\Stock\Hydrators\StockHydrateGrossWeightFromTradeUnits;
use App\Stubs\Migrations\HasDangerousGoodsFields;
use App\Actions\GrpAction;
use App\Actions\Helpers\Brand\AttachBrandToModel;
use App\Actions\Helpers\Tag\AttachTagsToModel;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Goods\TradeUnit;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use App\Stubs\Migrations\HasProductInformation;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateTradeUnit extends GrpAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithGoodsEditAuthorisation;
    use HasDangerousGoodsFields;
    use HasProductInformation;


    private TradeUnit $tradeUnit;

    public function handle(TradeUnit $tradeUnit, array $modelData): TradeUnit
    {
        if (Arr::has($modelData, 'name_i8n')) {
            UpdateTradeUnitTranslationsFromUpdate::make()->action($tradeUnit, [
                'translations' => [
                    'name' => Arr::pull($modelData, 'name_i8n')
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_title_i8n')) {
            UpdateTradeUnitTranslationsFromUpdate::make()->action($tradeUnit, [
                'translations' => [
                    'description_title' => Arr::pull($modelData, 'description_title_i8n')
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_i8n')) {
            UpdateTradeUnitTranslationsFromUpdate::make()->action($tradeUnit, [
                'translations' => [
                    'description' => Arr::pull($modelData, 'description_i8n')
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_extra_i8n')) {
            UpdateTradeUnitTranslationsFromUpdate::make()->action($tradeUnit, [
                'translations' => [
                    'description_extra' => Arr::pull($modelData, 'description_extra_i8n')
                ]
            ]);
        }

        if (Arr::has($modelData, 'tags')) {
            AttachTagsToModel::make()->action($tradeUnit, [
                'tags_id' => Arr::pull($modelData, 'tags')
            ]);
        }

        if (Arr::has($modelData, 'brands')) {
            AttachBrandToModel::make()->action($tradeUnit, [
                'brand_id' => Arr::pull($modelData, 'brands')
            ]);
        }

        $tradeUnit = $this->update($tradeUnit, $modelData, ['data', 'marketing_dimensions']);


        if ($tradeUnit->wasChanged('gross_weight')) {
            foreach ($tradeUnit->stocks as $stock) {
                StockHydrateGrossWeightFromTradeUnits::dispatch($stock);
            }
            foreach ($tradeUnit->products as $product) {
                ProductHydrateGrossWeightFromTradeUnits::dispatch($product);
            }
        }

        if ($tradeUnit->wasChanged('marketing_weight')) {
            foreach ($tradeUnit->products as $product) {
                ProductHydrateMarketingWeightFromTradeUnits::dispatch($product);
            }
        }

        if ($tradeUnit->wasChanged('marketing_ingredients')) {
            foreach ($tradeUnit->products as $product) {
                ProductHydrateMarketingIngredientsFromTradeUnits::dispatch($product);
            }
        }

        if ($tradeUnit->wasChanged('marketing_dimensions')) {
            foreach ($tradeUnit->products as $product) {
                ProductHydrateMarketingDimensionFromTradeUnits::dispatch($product);
            }
        }

        $dangerousGoodsFields     = $this->getDangerousGoodsFieldNames();
        $productInformationFields = $this->getProductInformationFieldNames();

        $fieldsForProductsUpdated = false;

        foreach (array_merge($dangerousGoodsFields, $productInformationFields) as $field) {
            if ($tradeUnit->wasChanged($field)) {
                $fieldsForProductsUpdated = true;
                break;
            }
        }

        if ($fieldsForProductsUpdated) {
            foreach ($tradeUnit->products as $product) {
                ProductHydrateTradeUnitsFields::dispatch($product);
            }
        }

        if ($tradeUnit->wasChanged('barcode')) {
            foreach ($tradeUnit->products as $product) {
                ProductHydrateBarcodeFromTradeUnit::dispatch($product);
            }
        }

        return $tradeUnit;
    }

    public function rules(): array
    {
        $rules = [
            'code'                         => [
                'sometimes',
                'required',
                'max:64',
                $this->strict ? new AlphaDashDot() : 'string',
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'trade_units',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->tradeUnit->id
                        ],

                    ]
                ),
            ],
            'name'                         => ['sometimes', 'required', 'string', 'max:255'],
            'description'                  => ['sometimes', 'required', 'string', 'max:1024'],
            'barcode'                      => ['sometimes', 'required'],
            'gross_weight'                 => ['sometimes', 'required', 'numeric'],
            'net_weight'                   => ['sometimes', 'required', 'numeric'],
            'marketing_weight'             => ['sometimes', 'required', 'numeric'],
            'marketing_dimensions'         => ['sometimes', 'required'],
            'type'                         => ['sometimes', 'required'],
            'image_id'                     => ['sometimes', 'required', Rule::exists('media', 'id')->where('group_id', $this->group->id)],
            'data'                         => ['sometimes', 'required'],

            // Dangerous goods string fields
            'un_number'                    => ['sometimes', 'nullable', 'string'],
            'un_class'                     => ['sometimes', 'nullable', 'string'],
            'packing_group'                => ['sometimes', 'nullable', 'string'],
            'proper_shipping_name'         => ['sometimes', 'nullable', 'string'],
            'hazard_identification_number' => ['sometimes', 'nullable', 'string'],
            'gpsr_manufacturer'            => ['sometimes', 'nullable', 'string'],
            'gpsr_eu_responsible'          => ['sometimes', 'nullable', 'string'],
            'gpsr_warnings'                => ['sometimes', 'nullable', 'string'],
            'gpsr_manual'                  => ['sometimes', 'nullable', 'string'],
            'gpsr_class_category_danger'   => ['sometimes', 'nullable', 'string'],
            'gpsr_class_languages'         => ['sometimes', 'nullable', 'string'],

            // Dangerous goods boolean fields
            'pictogram_toxic'              => ['sometimes', 'boolean'],
            'pictogram_corrosive'          => ['sometimes', 'boolean'],
            'pictogram_explosive'          => ['sometimes', 'boolean'],
            'pictogram_flammable'          => ['sometimes', 'boolean'],
            'pictogram_gas'                => ['sometimes', 'boolean'],
            'pictogram_environment'        => ['sometimes', 'boolean'],
            'pictogram_health'             => ['sometimes', 'boolean'],
            'pictogram_oxidising'          => ['sometimes', 'boolean'],
            'pictogram_danger'             => ['sometimes', 'boolean'],
            'cpnp_number'                  => ['sometimes', 'nullable', 'string'],
            'country_of_origin'            => ['sometimes', 'nullable', 'string'],
            'tariff_code'                  => ['sometimes', 'nullable', 'string'],
            'duty_rate'                    => ['sometimes', 'nullable', 'string'],
            'hts_us'                       => ['sometimes', 'nullable', 'string'],
            'marketing_ingredients'        => ['sometimes', 'nullable', 'string'],
            'name_i8n'                     => ['sometimes', 'array'],
            'description_title_i8n'        => ['sometimes', 'array'],
            'description_i8n'              => ['sometimes', 'array'],
            'description_extra_i8n'        => ['sometimes', 'array'],
            'tags'                         => ['sometimes', 'array'],
            'brands'                       => ['sometimes'],

        ];

        if (!$this->strict) {
            $rules['gross_weight']     = ['sometimes', 'nullable', 'numeric'];
            $rules['net_weight']       = ['sometimes', 'nullable', 'numeric'];
            $rules['marketing_weight'] = ['sometimes', 'nullable', 'numeric'];


            //            unset($rules['marketing_dimensions']);
            //            unset($rules['gross_weight']);
            //            unset($rules['net_weight']);
            //            unset($rules['marketing_weight']);


            $rules                     = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(TradeUnit $tradeUnit, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): TradeUnit
    {
        $this->asAction = true;
        $this->strict   = $strict;

        if (!$audit) {
            TradeUnit::disableAuditing();
        }
        $this->tradeUnit = $tradeUnit;

        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($tradeUnit->group, $modelData);

        return $this->handle($tradeUnit, $this->validatedData);
    }

    public function asController(TradeUnit $tradeUnit, ActionRequest $request): TradeUnit
    {
        $this->tradeUnit = $tradeUnit;
        $this->initialisation($tradeUnit->group, $request);

        return $this->handle($tradeUnit, $this->validatedData);
    }
}
