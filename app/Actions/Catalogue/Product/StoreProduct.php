<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:05:20 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Asset\StoreAsset;
use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateForSale;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateProductVariants;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Enums\Catalogue\Asset\AssetTypeEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Product\ProductTradeConfigEnum;
use App\Enums\Catalogue\Product\ProductUnitRelationshipType;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\Organisation;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreProduct extends OrgAction
{
    use WithProductHydrators;
    use WithNoStrictRules;

    private AssetStateEnum|null $state = null;

    /**
     * @throws \Throwable
     */
    public function handle(Shop|ProductCategory $parent, array $modelData): Product
    {
        $orgStocks = Arr::pull($modelData, 'org_stocks', []);


        if (count($orgStocks) == 1) {
            $units = $orgStocks[array_key_first($orgStocks)]['quantity'];
        } else {
            $units = 1;
        }


        data_set($modelData, 'units', $units);
        data_set($modelData, 'unit_relationship_type', $this->getUnitRelationshipType($orgStocks));
        data_set($modelData, 'organisation_id', $parent->organisation_id);
        data_set($modelData, 'group_id', $parent->group_id);

        if ($parent instanceof Shop) {
            $shop = $parent;
            data_set($modelData, 'shop_id', $parent->id);
        } else {
            $shop = $parent->shop;
            data_set($modelData, 'shop_id', $parent->shop_id);
            data_set($modelData, 'department_id', $parent->department_id);

            if ($parent->type == ProductCategoryTypeEnum::FAMILY) {
                data_set($modelData, 'family_id', $parent->id);
            }
            if ($parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
                data_set($modelData, 'department_id', $parent->id);
            }
        }
        data_set($modelData, 'currency_id', $shop->currency_id);


        $product = DB::transaction(function () use ($shop, $modelData, $orgStocks) {
            /** @var Product $product */
            $product = $shop->products()->create($modelData);
            $product = ProductHydrateForSale::run($product);

            if ($product->is_main) {
                $product->updateQuietly([
                    'main_product_id' => $product->id
                ]);
            }

            $product->stats()->create();
            $product->refresh();


            $product = $this->createAsset($product);
            return $this->associateOrgStocks($product, $orgStocks);
        });

        ProductHydrateProductVariants::dispatch($product->mainProduct)->delay($this->hydratorsDelay);
        $this->productHydrators($product);


        return $product;
    }


    private function associateOrgStocks(Product $product, array $orgStocks): Product
    {
        $product->orgStocks()->sync($orgStocks);

        $tradeUnits = [];
        foreach ($product->orgStocks as $orgStock) {
            foreach ($orgStock->tradeUnits as $tradeUnit) {
                $tradeUnits[$tradeUnit->id] = [
                    'quantity' => $orgStock->pivot->quantity * $tradeUnit->pivot->quantity,
                ];
            }
        }

        foreach ($tradeUnits as $tradeUnitId => $tradeUnitData) {
            $tradeUnit = TradeUnit::find($tradeUnitId);
            $product->tradeUnits()->attach(
                $tradeUnit,
                [
                    'quantity' => $tradeUnitData['quantity'],
                    'notes'    => Arr::get($tradeUnitData, 'notes'),
                ]
            );
        }

        return $product;
    }

    private function createAsset(Product $product): Product
    {
        $asset = StoreAsset::run(
            $product,
            [
                'type'    => AssetTypeEnum::PRODUCT,
                'is_main' => $product->is_main,
                'state'   => match ($product->state) {
                    ProductStateEnum::IN_PROCESS => AssetStateEnum::IN_PROCESS,
                    ProductStateEnum::ACTIVE => AssetStateEnum::ACTIVE,
                    ProductStateEnum::DISCONTINUING => AssetStateEnum::DISCONTINUING,
                    ProductStateEnum::DISCONTINUED => AssetStateEnum::DISCONTINUED,
                }
            ],
            $this->hydratorsDelay
        );

        $product->updateQuietly(
            [
                'asset_id' => $asset->id
            ]
        );
        $historicAsset = StoreHistoricAsset::run(
            $product,
            [
                'source_id' => $product->historic_source_id
            ],
            $this->hydratorsDelay
        );

        $asset->updateQuietly(
            [
                'current_historic_asset_id' => $historicAsset->id,
            ]
        );
        $product->updateQuietly(
            [
                'current_historic_asset_id' => $historicAsset->id,
            ]
        );

        return $product;
    }

    public function getUnitRelationshipType(array $orgStocks): ?ProductUnitRelationshipType
    {
        if (count($orgStocks) == 1) {
            return ProductUnitRelationshipType::SINGLE;
        } elseif (count($orgStocks) > 1) {
            return ProductUnitRelationshipType::MULTIPLE;
        }

        return null;
    }

    public function rules(): array
    {
        $rules = [
            'code'               => [
                'required',
                'max:32',
                new AlphaDashDot(),
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'assets',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'state', 'operator' => '!=', 'value' => ProductStateEnum::DISCONTINUED->value],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'name'               => ['required', 'max:250', 'string'],
            'state'              => ['sometimes', 'required', Rule::enum(ProductStateEnum::class)],
            'family_id'          => [
                'sometimes',
                'required',
                Rule::exists('product_categories', 'id')
                    ->where('shop_id', $this->shop->id)
                    ->where('type', ProductCategoryTypeEnum::FAMILY)
            ],
            'department_id'      => [
                'sometimes',
                'required',
                Rule::exists('product_categories', 'id')
                    ->where('shop_id', $this->shop->id)
                    ->where('type', ProductCategoryTypeEnum::DEPARTMENT)
            ],
            'image_id'           => ['sometimes', 'required', Rule::exists('media', 'id')->where('group_id', $this->organisation->group_id)],
            'price'              => ['required', 'numeric', 'min:0'],
            'unit'               => ['sometimes', 'required', 'string'],
            'rrp'                => ['sometimes', 'required', 'numeric', 'min:0'],
            'description'        => ['sometimes', 'required', 'max:1500'],
            'data'               => ['sometimes', 'array'],
            'settings'           => ['sometimes', 'array'],
            'is_main'            => ['required', 'boolean'],
            'main_product_id'    => [
                'sometimes',
                'nullable',
                Rule::exists('products', 'id')
                    ->where('shop_id', $this->shop->id)
            ],
            'variant_ratio'      => ['sometimes', 'required', 'numeric', 'gt:0'],
            'variant_is_visible' => ['sometimes', 'required', 'boolean'],

        ];

        if ($this->state == ProductStateEnum::DISCONTINUED) {
            $rules['code'] = [
                'required',
                'max:32',
                'alpha_dash',
            ];
        }


        $rules['org_stocks'] = ['present', 'array'];

        if (!$this->strict) {
            $rules                       = $this->noStrictStoreRules($rules);
            $rules['historic_source_id'] = ['sometimes', 'required', 'string', 'max:255'];
            $rules['state']              = ['required', Rule::enum(ProductStateEnum::class)];
            $rules['status']             = ['required', Rule::enum(ProductStatusEnum::class)];
            $rules['trade_config']       = ['required', Rule::enum(ProductTradeConfigEnum::class)];
        }

        return $rules;
    }


    /**
     * @throws \Throwable
     */
    public function inShop(Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);
        $this->handle($shop, $this->validatedData);

        return Redirect::route('grp.org.shops.show.catalogue.products.index', $shop);
    }

    /**
     * @throws \Throwable
     */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($fulfilment->shop, $request);
        $product = $this->handle($fulfilment->shop, $this->validatedData);

        return Redirect::route('grp.org.fulfilments.show.catalogue.physical_goods.show', [
            'organisation' => $organisation->slug,
            'fulfilment'   => $fulfilment->slug,
            'product'      => $product->slug
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function inFamily(Organisation $organisation, Shop $shop, ProductCategory $family, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);
        $this->handle($family, $this->validatedData);

        return Redirect::route('grp.org.shops.show.catalogue.families.show.products.index', [$organisation->slug, $shop->slug, $family->slug]);
    }

    /**
     * @throws \Throwable
     */
    public function action(Shop|ProductCategory $parent, array $modelData, int $hydratorsDelay = 0, $strict = true, $audit = true): Product
    {
        if (!$audit) {
            Product::disableAuditing();
        }

        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;

        if ($parent instanceof Shop) {
            $shop = $parent;
        } else {
            $shop = $parent->shop;
        }

        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($parent, $this->validatedData);
    }

}
