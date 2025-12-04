<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Jun 2024 09:05:18 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Asset\UpdateAssetFromModel;
use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\Catalogue\Product\Search\ProductRecordSearch;
use App\Actions\Catalogue\Product\Traits\WithProductOrgStocks;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateExclusiveProducts;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\CloseWebpage;
use App\Actions\Web\Webpage\Luigi\ReindexWebpageLuigiData;
use App\Actions\Web\Webpage\ReopenWebpage;
use App\Actions\Web\Webpage\UpdateWebpage;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Product\ProductTradeConfigEnum;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Http\Resources\Catalogue\ProductResource;
use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use App\Stubs\Migrations\HasDangerousGoodsFields;
use App\Stubs\Migrations\HasProductInformation;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

class UpdateProduct extends OrgAction
{
    use WithActionUpdate;
    use WithProductHydrators;
    use WithNoStrictRules;
    use WithProductOrgStocks;
    use HasDangerousGoodsFields;
    use HasProductInformation;

    private Product $product;

    public function handle(Product $product, array $modelData): Product
    {
        $webpageData = [];
        $newData = [];
        $oldData = $product->toArray();

        if (Arr::has($modelData, 'webpage_title')) {
            $webpageData['title'] = Arr::pull($modelData, 'webpage_title');
        }
        if (Arr::has($modelData, 'webpage_description')) {
            $webpageData['description'] = Arr::pull($modelData, 'webpage_description');
        }
        if (Arr::has($modelData, 'webpage_breadcrumb_label')) {
            $webpageData['breadcrumb_label'] = Arr::pull($modelData, 'webpage_breadcrumb_label');
        }

        $oldIsOutOfStock = $product->available_quantity > 0;

        $oldHistoricProduct = $product->current_historic_asset_id;

        if (Arr::has($modelData, 'family_id')) {
            UpdateProductFamily::make()->action($product, [
                'family_id' => Arr::pull($modelData, 'family_id'),
            ]);
        }

        // todo: remove this after total aurora migration
        if (!$this->strict) {
            $orgStocks = null;

            if (Arr::has($modelData, 'org_stocks')) {
                $orgStocksRaw = Arr::pull($modelData, 'org_stocks', []);


                $orgStocksRaw = array_column($orgStocksRaw, null, 'org_stock_id');

                $orgStocksRaw = array_map(function ($item) {
                    $filtered             = Arr::only($item, ['org_stock_id', 'quantity', 'notes']);
                    $filtered['quantity'] = (float)$filtered['quantity']; // or (int) if you want integers

                    return $filtered;
                }, $orgStocksRaw);

                $orgStocks = $orgStocksRaw;
            }

            if (Arr::has($modelData, 'well_formatted_org_stocks')) {
                $orgStocks = Arr::pull($modelData, 'well_formatted_org_stocks', []);
            }

            if ($orgStocks !== null) {
                $this->syncOrgStocksToBeDeleted($product, $orgStocks);
            }
        } elseif (Arr::has($modelData, 'trade_units')) {
            $product = SyncProductTradeUnits::run($product, Arr::pull($modelData, 'trade_units'));
        }


        $assetData = [];
        if (Arr::has($modelData, 'follow_master')) {
            data_set($assetData, 'follow_master', Arr::pull($modelData, 'follow_master'));
        }

        if (Arr::has($modelData, 'name_i8n')) {
            UpdateProductTranslations::make()->action($product, [
                'translations' => [
                    'name' => Arr::pull($modelData, 'name_i8n')
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_title_i8n')) {
            UpdateProductTranslations::make()->action($product, [
                'translations' => [
                    'description_title' => Arr::pull($modelData, 'description_title_i8n')
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_i8n')) {
            UpdateProductTranslations::make()->action($product, [
                'translations' => [
                    'description' => Arr::pull($modelData, 'description_i8n')
                ]
            ]);
        }

        if (Arr::has($modelData, 'description_extra_i8n')) {
            UpdateProductTranslations::make()->action($product, [
                'translations' => [
                    'description_extra' => Arr::pull($modelData, 'description_extra_i8n')
                ]
            ]);
        }

        if (Arr::hasAny($modelData, ['is_for_sale'])) {
            data_set($modelData, 'not_for_sale_since', $modelData['is_for_sale'] ? null : Carbon::now('UTC'));
            // For auditing | Ignore not_for_sale_since
            $newData = array_merge($newData, Arr::except($modelData, ['not_for_sale_since', 'out_of_stock_since', 'back_in_stock_since']));
        }

        $product = $this->update($product, $modelData);
        $changed = Arr::except($product->getChanges(), ['updated_at', 'last_fetched_at']);


        if (Arr::hasAny($changed, ['is_for_sale','state'])) {
            $product = ProductHydrateAvailableQuantity::run($product);
        }

        if ($product->webpage && !empty($webpageData)) {
            UpdateWebpage::make()->action($product->webpage, $webpageData);
        }

        if (Arr::has($changed, 'name')) {
            UpdateProductAndMasterTranslations::make()->action($product, [
                'translations' => [
                    'name' => [$product->shop->language->code => Arr::pull($modelData, 'name')]
                ]
            ]);
        }

        if (Arr::has($changed, 'is_for_sale') && $product->webpage) {

            if ($product->is_for_sale && $product->webpage->state == WebPageStateEnum::CLOSED) {
                ReopenWebpage::run($product->webpage);
            }

            if (!$product->is_for_sale && $product->webpage->state == WebPageStateEnum::LIVE) {


                CloseWebpage::make()->action(
                    $product->webpage,
                    [
                        'redirect_type' => RedirectTypeEnum::TEMPORAL,
                        'to_webpage_id' => $product->webpage->website->storefront_id
                    ]
                );
            }



        }


        if (Arr::has($changed, 'is_for_sale') || $newData) {
            $product->auditEvent    = 'update';
            $product->isCustomEvent = true;

            $product->auditCustomOld = array_intersect_key($oldData, $newData);

            $product->auditCustomNew = $newData;

            Event::dispatch(new AuditCustom($product));
        }

        if (Arr::has($changed, 'description_title')) {
            UpdateProductAndMasterTranslations::make()->action($product, [
                'translations' => [
                    'description_title' => [$product->shop->language->code => Arr::pull($modelData, 'description_title')]
                ]
            ]);
        }

        if (Arr::has($changed, 'description')) {
            UpdateProductAndMasterTranslations::make()->action($product, [
                'translations' => [
                    'description' => [$product->shop->language->code => Arr::pull($modelData, 'description')]
                ]
            ]);
        }

        if (Arr::has($changed, 'description_extra')) {
            UpdateProductAndMasterTranslations::make()->action($product, [
                'translations' => [
                    'description_extra' => [$product->shop->language->code => Arr::pull($modelData, 'description_extra')]
                ]
            ]);
        }

        if (Arr::hasAny($changed, ['name', 'code', 'price', 'units', 'unit'])) {
            $historicAsset = StoreHistoricAsset::run($product, [], $this->hydratorsDelay);

            $product->updateQuietly(
                [
                    'current_historic_asset_id' => $historicAsset->id,
                ]
            );
        }

        UpdateAssetFromModel::run($product->asset, $assetData, $this->hydratorsDelay);

        if (Arr::hasAny($changed, ['state', 'status', 'exclusive_for_customer_id'])) {
            $this->productHydrators($product);
        }

        if (Arr::has($changed, 'exclusive_for_customer_id')) {
            CustomerHydrateExclusiveProducts::dispatch($product->exclusiveForCustomer)->delay($this->hydratorsDelay);
        }

        if (Arr::hasAny(
            $changed,
            [
                'code',
                'name',
                'description',
                'state',
                'price',
            ]
        )) {
            ProductRecordSearch::dispatch($product);
        }

        $isOutOfStock = $product->available_quantity > 0;


        $fieldsUsedInLuigi = [
            'code',
            'name',
            'description',
            'state',
            'status',
            'price',
        ];

        if ($product->webpage
            && (Arr::hasAny(
                $changed,
                $fieldsUsedInLuigi
            )
                || $isOutOfStock != $oldIsOutOfStock)
        ) {
            ReindexWebpageLuigiData::dispatch($product->webpage)->delay(60 * 15);
        }


        $fieldsUsedInWebpages = array_merge(
            $fieldsUsedInLuigi,
            $this->getDangerousGoodsFieldNames(),
            $this->getProductInformationFieldNames()
        );

        if ($product->webpage
            && (Arr::hasAny(
                $changed,
                $fieldsUsedInWebpages
            )
                || $isOutOfStock != $oldIsOutOfStock)
        ) {
            BreakProductInWebpagesCache::dispatch($product)->delay(15);
        }

        if (Arr::has($changed, 'available_quantity')) {
            $product->updateQuietly([
                'available_quantity_updated_at' => now()
            ]);
        }

        if (Arr::has($changed, 'master_product_id')) {
            $product->asset->updateQuietly([
                'master_asset_id' => $product->master_product_id
            ]);
        }

        if (Arr::has($changed, 'price')) {
            $product->updateQuietly([
                'price_updated_at' => now()
            ]);
        }

        if ($oldHistoricProduct != $product->current_historic_asset_id) {
            UpdateHistoricProductInBasketTransactions::dispatch($product);
        }

        return $product;
    }

    public function rules(): array
    {
        $rules = [
            'code'                      => [
                'sometimes',
                'required',
                'max:32',
                new AlphaDashDot(),
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'products',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                        ['column' => 'id', 'value' => $this->product->id, 'operator' => '!=']
                    ]
                ),
            ],
            'name'                      => ['sometimes', 'required', 'max:250', 'string'],
            'price'                     => ['sometimes', 'required', 'numeric', 'min:0'],
            'unit_price'                => ['sometimes', 'required', 'numeric', 'min:0'],
            'description'               => ['sometimes', 'required', 'max:1500'],
            'description_title'         => ['sometimes', 'nullable', 'max:255'],
            'description_extra'         => ['sometimes', 'nullable', 'max:65500'],
            'rrp'                       => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'data'                      => ['sometimes', 'array'],
            'settings'                  => ['sometimes', 'array'],
            'status'                    => ['sometimes', 'required', Rule::enum(ProductStatusEnum::class)],
            'state'                     => ['sometimes', 'required', Rule::enum(ProductStateEnum::class)],
            'trade_config'              => ['sometimes', 'required', Rule::enum(ProductTradeConfigEnum::class)],
            'follow_master'             => ['sometimes', 'boolean'],
            'cost_price_ratio'          => ['sometimes', 'numeric', 'min:0'],
            'family_id'                 => ['sometimes', 'nullable', Rule::exists('product_categories', 'id')->where('shop_id', $this->shop->id)],
            'master_product_id'         => ['sometimes', 'nullable', 'integer', Rule::exists('master_assets', 'id')->where('master_shop_id', $this->shop->master_shop_id)],
            'barcode'                   => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::exists('barcodes', 'number')
                    ->whereNull('deleted_at')
            ],
            'webpage_id'                => ['sometimes', 'integer', 'nullable', Rule::exists('webpages', 'id')->where('shop_id', $this->shop->id)],
            'url'                       => ['sometimes', 'nullable', 'string', 'max:250'],
            'units'                     => ['sometimes', 'numeric'],
            'unit'                      => ['sometimes', 'string'],
            'exclusive_for_customer_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('shop__id', $this->shop->id)
            ],

            'name_i8n'              => ['sometimes', 'array'],
            'description_title_i8n' => ['sometimes', 'array'],
            'description_i8n'       => ['sometimes', 'array'],
            'description_extra_i8n' => ['sometimes', 'array'],
            'gross_weight'          => ['sometimes', 'numeric'],
            'marketing_weight'      => ['sometimes', 'numeric'],
            'marketing_dimensions'  => ['sometimes'],

            'cpnp_number'                  => ['sometimes', 'nullable', 'string'],
            'ufi_number'                   => ['sometimes', 'nullable', 'string'],
            'scpn_number'                  => ['sometimes', 'nullable', 'string'],
            'country_of_origin'            => ['sometimes', 'nullable', 'string'],
            'origin_country_id'            => ['sometimes', 'nullable', 'exists:countries,id'],
            'tariff_code'                  => ['sometimes', 'nullable', 'string'],
            'duty_rate'                    => ['sometimes', 'nullable', 'string'],
            'hts_us'                       => ['sometimes', 'nullable', 'string'],


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

            'webpage_title'            => ['sometimes', 'string'],
            'webpage_description'      => ['sometimes', 'string'],
            'webpage_breadcrumb_label' => ['sometimes', 'string', 'max:40'],

            // Sale Status & Webpage
            'is_for_sale'               => ['sometimes', 'boolean'],
            'not_for_sale_from_master' => ['sometimes', 'boolean'],
            'not_for_sale_from_trade_unit' => ['sometimes', 'boolean'],

        ];


        if (!$this->strict) {
            $rules['org_stocks']                = ['sometimes', 'nullable', 'array'];
            $rules['gross_weight']              = ['sometimes', 'integer', 'gt:0'];
            $rules['exclusive_for_customer_id'] = ['sometimes', 'nullable', 'integer'];
            $rules['well_formatted_org_stocks'] = ['sometimes', 'present', 'array'];


            $rules = $this->noStrictUpdateRules($rules);
        } else {
            $rules['trade_units'] = ['sometimes', 'present', 'array'];
        }

        return $rules;
    }

    public function asController(Product $product, ActionRequest $request): Product
    {
        $this->product = $product;
        $this->initialisationFromShop($product->shop, $request);

        return $this->handle($product, $this->validatedData);
    }

    public function action(Product $product, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Product
    {
        if (!$audit) {
            Product::disableAuditing();
        }

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->product        = $product;
        $this->strict         = $strict;

        $this->initialisationFromShop($product->shop, $modelData);

        return $this->handle($product, $this->validatedData);
    }

    public function jsonResponse(Product $product): ProductResource
    {
        return new ProductResource($product);
    }
}
