<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 02:37:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Asset\UpdateAsset;
use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\Ordering\Order\RecalculateTotalsOrdersInBasket;
use App\Actions\Catalogue\Product\CloneProductImagesFromTradeUnits;
use App\Actions\Catalogue\Product\SyncProductTradeUnits;
use App\Actions\Catalogue\Product\Traits\WithCustomTradeUnitAudits;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Product\UpdateProductFamily;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateAssets;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateMasterPricesRRPtoChild;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterAssets;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterFamilyHydrateMasterAssets;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterAssets;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateNumberMismatches;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterAssets;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithLineTaxCategories;
use App\Actions\Traits\WithMasterAssetTradeUnits;
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Helpers\Language;
use App\Models\Helpers\TaxCategory;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterAsset extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithMastersEditAuthorisation;
    use WithLineTaxCategories;
    use WithMasterAssetTradeUnits;
    use WithCustomTradeUnitAudits;

    private MasterAsset $masterAsset;

    /** Only a human editing in the UI is guarded against overlapping sweeps; the seeder sequences itself. */
    private bool $guardTaxSweep = false;

    /**
     * @throws \Throwable
     */
    public function handle(MasterAsset $masterAsset, array $modelData): MasterAsset
    {
        $oldMasterAsset      = clone($masterAsset);
        $oldMismatchDetected = $oldMasterAsset->mismatch_detected;

        if ($this->guardTaxSweep && Arr::hasAny($modelData, ['tax_preset', 'tax_category'])) {
            /**
             * Two sweeps over the same baskets interleave their recalculations; the second
             * change must wait for the first to finish, exactly like the price pipelines.
             */
            $runningSweep = TaxPresetBasketProgress::get($masterAsset);
            if ($runningSweep && $runningSweep['state'] != 'finished') {
                throw ValidationException::withMessages([
                    'tax_preset' => __('The previous tax change is still repricing baskets, try again when it finishes.'),
                ]);
            }
        }

        if (Arr::has($modelData, 'tax_preset')) {
            $presetName = (string)Arr::pull($modelData, 'tax_preset');
            /** "custom" is display only, re-posting it must not clear the imported map. */
            if ($presetName != 'custom') {
                data_set($modelData, 'tax_category', $presetName == 'standard' || $presetName == '' ? [] : $this->expandTaxPreset($presetName));
            }
        }

        if (Arr::has($modelData, 'tax_category')) {
            $taxCategoryMap = $this->normaliseTaxCategoryMap(Arr::get($modelData, 'tax_category'));
            data_set($modelData, 'tax_category', $taxCategoryMap);

            /**
             * The preset column exists so "everything that is food" can be re-expanded in
             * mass when a rate changes; it is derived, never trusted from the input. A map
             * no preset produces (imported, or hand set before presets existed) stores null.
             */
            $inferredPreset = $this->inferTaxPreset($taxCategoryMap);
            data_set($modelData, 'tax_preset', $inferredPreset == 'custom' ? null : $inferredPreset);
        }

        if (Arr::has($modelData, 'master_family_id')) {
            $masterFamily = null;
            if ($modelData['master_family_id']) {
                $masterFamily = MasterProductCategory::where('id', $modelData['master_family_id'])->first();
            }
            if ($masterFamily) {
                data_set($modelData, 'master_department_id', $masterFamily->master_department_id);
                data_set($modelData, 'master_sub_department_id', $masterFamily->master_sub_department_id);
            } else {
                data_set($modelData, 'master_department_id', null);
                data_set($modelData, 'master_sub_department_id', null);
            }
        }

        if (Arr::has($modelData, 'is_for_sale')) {
            if (!Arr::get($modelData, 'is_for_sale')) {
                data_set($modelData, 'status', false);
            }

            data_set($modelData, 'not_for_sale_since', Arr::get($modelData, 'is_for_sale') ? now() : null);
        }

        if (Arr::has($modelData, 'name_i8n')) {
            UpdateMasterProductTranslationsFromUpdate::make()->action($masterAsset, [
                'translations' => [
                    'name' => Arr::pull($modelData, 'name_i8n')
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_title_i8n')) {
            UpdateMasterProductTranslationsFromUpdate::make()->action($masterAsset, [
                'translations' => [
                    'description_title' => Arr::pull($modelData, 'description_title_i8n')
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_i8n')) {
            UpdateMasterProductTranslationsFromUpdate::make()->action($masterAsset, [
                'translations' => [
                    'description' => Arr::pull($modelData, 'description_i8n')
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_extra_i8n')) {
            UpdateMasterProductTranslationsFromUpdate::make()->action($masterAsset, [
                'translations' => [
                    'description_extra' => Arr::pull($modelData, 'description_extra_i8n')
                ]
            ]);
        }

        if (Arr::has($modelData, 'master_prices')) {
            $eurPrice = data_get($modelData, 'master_prices.EUR.value');
            if ($eurPrice) {
                data_set($modelData, 'price', $eurPrice);
            }
        }

        if (Arr::has($modelData, 'master_rrps')) {
            $eurRRP = data_get($modelData, 'master_rrps.EUR.value');
            if ($eurRRP) {
                data_set($modelData, 'rrp', $eurRRP);
            }
        }

        $tradeUnits = Arr::pull($modelData, 'trade_units', []);

        $masterAsset = DB::transaction(function () use ($masterAsset, $modelData, $tradeUnits) {
            $oldTradeUnitData = $masterAsset->tradeUnits;

            /** @var MasterAsset $masterAsset */
            if (!empty($tradeUnits)) {
                $this->processTradeUnits($masterAsset, $tradeUnits);
                $hasIndependentUnits = Arr::get($modelData, 'has_independent_units', $masterAsset->has_independent_units);

                $unitsFromTradeUnits = $this->getUnitsFromTradeUnits($tradeUnits);
                if (!$hasIndependentUnits && $unitsFromTradeUnits['units'] !== null) {
                    data_set($modelData, 'units', $unitsFromTradeUnits['units']);
                }
                /** A label typed in this same save wins over the one the composition suggests. */
                if (!Arr::has($modelData, 'unit')) {
                    data_set($modelData, 'unit', $unitsFromTradeUnits['unit']);
                }

                foreach ($masterAsset->products()->whereNot('products.not_follow_master_trade_units', true)->get() as $product) {
                    SyncProductTradeUnits::run($product, $tradeUnits);
                }

                data_set($modelData, 'mismatch_detected', false);
            }

            $this->update($masterAsset, $modelData);
            $masterAsset->refresh();

            $this->dispatchCustomAuditTradeUnit($masterAsset, $oldTradeUnitData);


            return ModelHydrateSingleTradeUnits::run($masterAsset);
        });

        CloneMasterAssetImagesFromTradeUnits::run($masterAsset);

        if ($oldMismatchDetected != $masterAsset->mismatch_detected) {
            $masterFamily = $masterAsset->masterFamily;
            $masterFamily?->update(
                [
                    'mismatch_detected' => $masterFamily->masterAssets()->where('mismatch_detected', true)->exists()
                ]
            );

            MasterShopHydrateNumberMismatches::run($masterAsset->masterShop);
        }

        if ($masterAsset->wasChanged('unit')) {
            $english = Language::where('code', 'en')->first();

            /**
             * The unit is a label a customer reads, so it rides the same rails as the name:
             * copied verbatim to shops that speak the language it was written in, machine
             * translated for the rest. A shop that has opted out of following the master
             * keeps whatever it set, in any language. How many trade units the master is
             * built from says nothing about that, so it does not gate the cascade.
             */
            foreach ($masterAsset->products as $product) {
                $shop = $product->shop;

                if ($shop->language_id == $english->id) {
                    UpdateProduct::run($product, [
                        'unit' => $masterAsset->unit,
                    ]);

                    continue;
                }

                if (!data_get($shop->settings, 'catalog.product_follow_master') || !$masterAsset->unit) {
                    continue;
                }

                UpdateProduct::run($product, [
                    'unit' => Translate::run($masterAsset->unit, $english, $shop->language, 'gpt-5-nano'),
                ]);
            }
        }

        if ($masterAsset->wasChanged('units')) {
            foreach ($masterAsset->products()->where('has_independent_units', false)->get() as $product) {
                UpdateProduct::run($product, [
                    'units' => $masterAsset->units,
                ]);
            }
        }

        if ($masterAsset->wasChanged('is_for_sale')) {
            foreach ($masterAsset->products as $product) {
                UpdateProduct::run($product, [
                    'is_for_sale'              => $masterAsset->is_for_sale,
                    'not_for_sale_from_master' => !$masterAsset->is_for_sale
                ]);
            }
        }

        if ($masterAsset->wasChanged('master_family_id')) {
            if ($masterAsset->masterFamily) {
                foreach ($masterAsset->products as $product) {
                    $family = $masterAsset->masterFamily->productCategories()->where('shop_id', $product->shop_id)->first();
                    UpdateProductFamily::run($product, [
                        'family_id' => $family ? $family->id : null
                    ]);
                }
            } else {
                foreach ($masterAsset->products as $product) {
                    UpdateProductFamily::run($product, [
                        'family_id' => null
                    ]);
                }
            }
        }

        if ($masterAsset->wasChanged(['master_prices', 'master_rrps'])) {
            MasterAssetHydrateMasterPricesRRPtoChild::run($masterAsset);

            NotifyMasterPriceDrift::dispatch($masterAsset)->delay($this->hydratorsDelay);
        }

        if ($masterAsset->wasChanged(['price', 'rrp', 'status'])) {
            MasterShopHydrateMasterAssets::dispatch($masterAsset->masterShop)->delay($this->hydratorsDelay);

            if ($masterAsset->wasChanged('status')) {
                GroupHydrateMasterAssets::dispatch($masterAsset->group)->delay($this->hydratorsDelay);
                if ($masterAsset->masterdepartment) {
                    MasterDepartmentHydrateMasterAssets::dispatch($masterAsset->masterDepartment)->delay($this->hydratorsDelay);
                }
                if ($masterAsset->masterFamily) {
                    MasterFamilyHydrateMasterAssets::dispatch($masterAsset->masterFamily)->delay($this->hydratorsDelay);
                }
            }
        }

        if ($masterAsset->wasChanged('tax_category')) {
            foreach ($masterAsset->assets as $asset) {
                UpdateAsset::run($asset, [
                    'tax_category' => $masterAsset->tax_category
                ]);
            }

            /**
             * Tax rides the same rails as a price change: a new historic freezes the new
             * treatment, baskets are moved onto it and recalculated, and every line already
             * sold keeps the historic - and the treatment - it was sold under. The affected
             * baskets are counted up front and each recalculation reports back, so the
             * person who pressed Food watches a progress bar instead of guessing.
             */
            $assetIds = [];
            foreach ($masterAsset->products as $product) {
                /**
                 * Faire and other external shops are taxed from the marketplace payload;
                 * their lines never read the map, so minting historics and sweeping their
                 * baskets would be churn at best and a payload overwrite at worst.
                 */
                if ($product->shop->type == ShopTypeEnum::EXTERNAL) {
                    continue;
                }

                $historicAsset = StoreHistoricAsset::run($product, [], $this->hydratorsDelay);
                $product->updateQuietly(['current_historic_asset_id' => $historicAsset->id]);

                $assetIds[] = $product->asset_id;
            }

            $basketOrderIds = $this->getTaxSweepBasketOrderIds($assetIds);

            TaxPresetBasketProgress::start($masterAsset, $basketOrderIds, $assetIds);

            /**
             * A giant basket (aws34545 carries 2,496 lines) recalculates for longer than the
             * urgent connection's retry_after, so the queue re-serves it mid-run and burns
             * its attempts. Oversized baskets go to the long-running connection, whose
             * retry_after was sized for exactly this. One grouped query, not one per basket.
             */
            $oversizedOrderIds = empty($basketOrderIds) ? [] : DB::table('transactions')
                ->whereIn('order_id', $basketOrderIds)
                ->whereNull('deleted_at')
                ->groupBy('order_id')
                ->havingRaw('count(*) > ?', [TaxPresetBasketProgress::OVERSIZED_LINES])
                ->pluck('order_id')
                ->all();

            foreach ($basketOrderIds as $orderId) {
                /** No held PendingDispatch: on the sync driver it only runs on destruct, and a variable keeps the last one alive past the sweep. */
                if (in_array($orderId, $oversizedOrderIds)) {
                    RecalculateTotalsOrdersInBasket::dispatch($orderId, null, null, null, $masterAsset->id)
                        ->onConnection('redis-long-running')
                        ->onQueue('long-running');
                } else {
                    RecalculateTotalsOrdersInBasket::dispatch($orderId, null, null, null, $masterAsset->id);
                }
            }

            /**
             * The watchdog goes on the queue after the reprices so its first run already sees
             * work done; it reconciles from the data, so dropped or dead jobs cannot wedge
             * the bar the way a decrementing counter did.
             */
            if ($basketOrderIds) {
                CheckTaxPresetBasketProgress::dispatch($masterAsset->id)->delay(5);
            }
        }

        if ($masterAsset->wasChanged(['name', 'description', 'description_title', 'description_extra', 'code', 'is_golden_product'])) {
            $english = Language::where('code', 'en')->first();

            foreach ($masterAsset->products as $product) {
                $shop            = $product->shop;
                $dataToBeUpdated = [];

                if ($masterAsset->wasChanged('is_golden_product')) {
                    $dataToBeUpdated['is_golden_product'] = $masterAsset->is_golden_product;
                }

                if (data_get($shop->settings, 'catalog.product_follow_master', false)) {
                    $shopLanguage    = $shop->language;

                    // Updates the affected field name using translation if follow_master_{field} is true
                    if ($masterAsset->wasChanged('name')) {
                        $dataToBeUpdated['name']             = Translate::run($masterAsset->name, $english, $shopLanguage, 'gpt-5-nano');
                        $dataToBeUpdated['is_name_reviewed'] = false;
                    }
                    if ($masterAsset->wasChanged('description_title')) {
                        $dataToBeUpdated['description_title']             = Translate::run($masterAsset->description_title, $english, $shopLanguage, 'gpt-5-nano');
                        $dataToBeUpdated['is_description_title_reviewed'] = false;
                    }
                    if ($masterAsset->wasChanged('description')) {
                        $dataToBeUpdated['description']             = Translate::run($masterAsset->description, $english, $shopLanguage, 'gpt-5-nano');
                        $dataToBeUpdated['is_description_reviewed'] = false;
                    }
                    if ($masterAsset->wasChanged('description_extra')) {
                        $dataToBeUpdated['description_extra']             = Translate::run($masterAsset->description_extra, $english, $shopLanguage, 'gpt-5-nano');
                        $dataToBeUpdated['is_description_extra_reviewed'] = false;
                    }
                }

                if ($dataToBeUpdated) {
                    UpdateProduct::make()->action($product, $dataToBeUpdated);
                }
            }
        }

        if ($masterAsset->wasChanged('is_for_sale') && $masterAsset->is_for_sale) {
            MasterAssetHydrateAssets::run($masterAsset->id);
        }


        if ($masterAsset->wasChanged('follow_trade_unit_media') && $masterAsset->follow_trade_unit_media && $masterAsset->is_single_trade_unit) {
            CloneMasterAssetImagesFromTradeUnits::run($masterAsset);
            foreach ($masterAsset->products as $product) {
                CloneProductImagesFromTradeUnits::run($product);
            }
        }

        return $masterAsset;
    }


    public function rules(): array
    {
        $rules = [
            'code'                         => [
                'sometimes',
                'required',
                'max:32',
                new AlphaDashDot(),
                new IUnique(
                    table: 'master_assets',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                        ['column' => 'id', 'value' => $this->masterAsset->id, 'operator' => '!=']
                    ]
                ),
            ],
            'trade_units'                  => ['sometimes', 'array', 'nullable'],
            'name'                         => ['sometimes', 'required', 'max:250', 'string'],
            'price'                        => ['sometimes', 'required', 'numeric', 'min:0'],
            'description'                  => ['sometimes', 'required', 'max:1500'],
            'description_title'            => ['sometimes', 'nullable', 'max:255'],
            'description_extra'            => ['sometimes', 'nullable', 'max:65500'],
            'rrp'                          => ['sometimes', 'required', 'numeric'],
            'unit'                         => ['sometimes', 'string'],
            'data'                         => ['sometimes', 'array'],
            'status'                       => ['sometimes', 'required', 'boolean'],
            'units'                        => ['sometimes', 'numeric', 'min:0'],

            'has_independent_units' => ['sometimes', 'boolean'],
            'master_family_id'             => [
                'sometimes',
                'nullable',
                Rule::exists('master_product_categories', 'id')
                    ->where('master_shop_id', $this->masterAsset->master_shop_id)
                    ->where('type', MasterProductCategoryTypeEnum::FAMILY)
            ],
            'name_i8n'                     => ['sometimes', 'array'],
            'description_title_i8n'        => ['sometimes', 'array'],
            'description_i8n'              => ['sometimes', 'array'],
            'description_extra_i8n'        => ['sometimes', 'array'],
            'is_for_sale'                  => ['sometimes', 'boolean'],
            'not_for_sale_from_trade_unit' => ['sometimes', 'boolean'],
            'follow_trade_unit_media'      => ['sometimes', 'boolean'],
            'tax_category'                 => ['sometimes', 'array'],
            'tax_preset'                   => ['sometimes', 'nullable', Rule::in([...array_keys($this->getTaxPresets()), 'standard', 'custom', ''])],
            // Master Prices
            'master_prices'                => ['sometimes', 'array'],
            'master_prices.*.value'        => ['sometimes', 'numeric', 'gt:0'],
            'master_prices.*.independent'  => ['sometimes', 'boolean'],
            // Master RRPs | values are totals (same basis as products.rrp), NOT per unit
            'master_rrps'                   => ['sometimes', 'array'],
            'master_rrps.*.value'           => ['sometimes', 'numeric', 'gt:0'],
            'master_rrps.*.independent'     => ['sometimes', 'boolean'],
            'is_golden_product'             => ['sometimes', 'boolean'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }


    /**
     * Everything arriving here is the stored shape, order-category-id => override-category-id
     * (the form itself posts a preset name, resolved before this runs). Unknown categories
     * are dropped rather than trusted, this value ends up multiplying invoices.
     *
     * @param  array<int|string, mixed>  $taxCategory
     *
     * @return array<int, int>
     */
    private function normaliseTaxCategoryMap(array $taxCategory): array
    {
        $knownIds = TaxCategory::pluck('id')->all();

        $map = [];
        foreach ($taxCategory as $orderTaxCategoryId => $lineTaxCategoryId) {
            $orderTaxCategoryId = (int)$orderTaxCategoryId;
            $lineTaxCategoryId  = (int)$lineTaxCategoryId;

            if (in_array($orderTaxCategoryId, $knownIds) && in_array($lineTaxCategoryId, $knownIds)) {
                $map[$orderTaxCategoryId] = $lineTaxCategoryId;
            }
        }

        return $map;
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterAsset $masterAsset, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): MasterAsset
    {
        $this->strict = $strict;
        if (!$audit) {
            MasterAsset::disableAuditing();
        }

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->masterAsset    = $masterAsset;

        $this->initialisationFromGroup($masterAsset->group, $modelData);

        return $this->handle($masterAsset, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(MasterAsset $masterAsset, ActionRequest $request): MasterAsset
    {
        $this->guardTaxSweep = true;
        $this->masterAsset   = $masterAsset;
        $this->initialisationFromGroup($masterAsset->group, $request);

        return $this->handle($masterAsset, $this->validatedData);
    }
}
