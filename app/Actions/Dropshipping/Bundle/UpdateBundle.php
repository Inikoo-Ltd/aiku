<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 19:36:35 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Bundle;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Product\UpdateProductImages;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Portfolio;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Faker\Factory as Faker;

class UpdateBundle extends OrgAction
{
    use WithNoStrictRules;
    use WithActionUpdate;
    use WithAttachMediaToModel;

    private Customer $customer;

    /**
     * @throws \Throwable
     */
    public function handle(Bundle $bundle, array $modelData): Bundle
    {
         return DB::transaction(function () use ($bundle, $modelData) {
            $tradeUnits = [];
            Arr::forget($modelData, 'id');

            /** @var Product $product */
            $product = $bundle->bundleable;
            $shopBundleDiscount = Arr::get($product->shop->settings, 'discount.bundle_discount_percentage', 10);

            $this->update($product, Arr::only($modelData, ['name', 'description', 'rrp']));

            /** @var array $mainMedia */
            $mainMedia = collect(Arr::get($modelData, 'images'))->where('is_main', true)->first();
            $images = collect(Arr::get($modelData, 'images'))->pluck('id');

            $selectedProducts = $this->processDelete($bundle, Arr::get($modelData, 'products'));

            foreach ($images as $imageId) {
                $existingMedia = Media::find($imageId);
                $this->attachMediaToModel($product, $existingMedia, 'image');
            }
            $mainMediaId = Arr::get($mainMedia, 'id');

            if (($product->image_id === null) && $mainMediaId) {
                UpdateProductImages::run($product, [
                    'image_id' => Arr::get($mainMedia, 'id'),
                ]);
            }

            if ($bundle->customerSalesChannel->platform->type === PlatformTypeEnum::MANUAL) {
                $this->update($bundle, [
                    'platform_status' => true,
                    'has_valid_platform_product_id' => true,
                    'exist_in_platform' => true
                ]);
            }

            if (Arr::get($modelData, 'payloadItems')) {
                $selectedBundleItems = Arr::get($modelData, 'payloadItems');

                foreach ($selectedBundleItems as $selectedBundleItem) {
                    $bundleItem = BundleItem::find($selectedBundleItem['bundle_item_id']);

                    /** @var Product $productSelected */
                    $productSelected = $bundleItem->item;

                    foreach ($productSelected->tradeUnits as $tradeUnit) {
                        $tradeUnits[] = [
                            'id' => $tradeUnit->id,
                            'quantity' => $selectedBundleItem['quantity']
                        ];
                    }

                    $this->update($bundleItem, [
                        'quantity' => $selectedBundleItem['quantity']
                    ]);
                }

                $productPrice = collect($selectedBundleItems)->sum(function ($selectedBundleItem) {
                    $bundleItem = BundleItem::find($selectedBundleItem['bundle_item_id']);
                    return $bundleItem->item->price * $selectedBundleItem['quantity'];
                });
                $productPrice = $productPrice * (1 - ($shopBundleDiscount / 100));

                $productRrp = collect($selectedBundleItems)->sum(function ($selectedBundleItem) {
                    $bundleItem = BundleItem::find($selectedBundleItem['bundle_item_id']);
                    return $bundleItem->item->rrp * $selectedBundleItem['quantity'];
                });
                $productRrp = $productRrp * (1 - ($shopBundleDiscount / 100));

                $portfolio = Portfolio::where('bundle_id', $bundle->id)->first();

                UpdateProduct::run($product, [
                    'trade_units' => $tradeUnits,
                    'price' => $productPrice,
                    'rrp' => $productRrp,
                ]);

                UpdatePortfolio::make()->action($portfolio, [
                    'selling_price' => $productRrp,
                    'customer_price' => $productRrp
                ]);
            }

            if(! blank($selectedProducts)) {
                $productSelected = Product::where('shop_id', $bundle->customer->shop_id)
                    ->whereIn('id', Arr::pluck($selectedProducts, 'product_id'))
                    ->get();

                $tradeUnits = array_merge($tradeUnits, $productSelected->map(function ($product) use ($selectedProducts) {
                    return $product->tradeUnits->map(function (TradeUnit $tradeUnit) use ($product, $selectedProducts) {
                        /** @var array $productQty */
                        $productQty = collect($selectedProducts)->where('product_id', $product->id)->first();

                        return [
                            'id' => $tradeUnit->id,
                            'quantity' => Arr::get($productQty, 'quantity')
                        ];
                    });
                })->collapse()->toArray());

                foreach ($selectedProducts as $selectedProduct) {
                    $bundleItem = BundleItem::where('bundle_id', $bundle->id)
                        ->where('item_type', class_basename(Product::class))
                        ->where('item_id', $selectedProduct['product_id'])
                        ->first();

                    if($bundleItem) {
                        continue;
                    }

                    $bundle->items()->create([
                        'item_id' => Arr::get($selectedProduct, 'product_id'),
                        'item_type' => class_basename(Product::class),
                        'quantity' => Arr::get($selectedProduct, 'quantity')
                    ]);
                }

                $calculatedPrice = CalculateBundleItemPriceDetails::run($bundle->customerSalesChannel, $modelData);

                UpdateProduct::make()->action($product, [
                    'trade_units' => $tradeUnits,
                    'price' => Arr::get($calculatedPrice, 'total_price'),
                    'rrp' => Arr::get($calculatedPrice, 'total_rrp')
                ]);
            }

            $bundle->refresh();

            return $bundle;
         });
    }

    public function processDelete(Bundle $bundle, array $selectedProducts): array
    {
        $productIds = collect($selectedProducts)->pluck('product_id')->toArray();
        $bundleItems = BundleItem::where('bundle_id', $bundle->id)->get();
        $productDiff = array_diff($bundleItems->pluck('item_id')->toArray(), $productIds);

        foreach ($selectedProducts as $key => $selectedProduct) {
            if (in_array($selectedProduct['product_id'], $productDiff)) {
                unset($selectedProducts[$key]);
            }
        }

        BundleItem::where('bundle_id', $bundle->id)
            ->whereIn('item_id', $productDiff)
            ->delete();

        return $selectedProducts;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'id' => ['nullable'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'rrp' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'images' => ['sometimes', 'array'],
            'images.*.id' => ['sometimes', 'integer', 'exists:media,id'],
            'images.*.is_main' => ['sometimes', 'boolean'],
            'payloadItems' => ['sometimes', 'array'],
            'payloadItems.*.bundle_item_id' => ['required', 'integer', 'exists:bundle_items,id'],
            'payloadItems.*.quantity' => ['required', 'integer', 'min:1'],
            'products' => ['sometimes', 'array'],
            'products.*.product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'products.*.quantity' => ['sometimes', 'integer', 'min:1'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Bundle $bundle, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Bundle
    {
        if (!$audit) {
            Portfolio::disableAuditing();
        }
        $this->asAction = true;
        $this->strict = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->customer = $bundle->customer;
        $this->initialisationFromShop($bundle->customer->shop, $modelData);

        return $this->handle($bundle, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Bundle $bundle, ActionRequest $request): Bundle
    {
        $this->customer = $bundle->customer;

        $this->initialisationFromShop($bundle->customer->shop, $request);

        return $this->handle($bundle, $this->validatedData);
    }

    public string $commandSignature = 'ds:bundle:update {bundle}';

    public function asCommand(Command $command): void
    {
        $bundle = Bundle::where('id', $command->argument('bundle'))->firstOrFail();

        $faker = Faker::create();
        $modelData = [
            'name' => $faker->name,
            'code' => $faker->bothify('B-####'),
            'price' => $faker->randomFloat(2, 10, 1000),
            'rrp' => $faker->randomFloat(2, 10, 1000),
            'description' => $faker->sentence(),
            'products' => [
                ['product_id' => 151812, 'quantity' => 1],
                ['product_id' => 411847, 'quantity' => 2],
                ['product_id' => 154425, 'quantity' => 3]
            ]
        ];

        $bundle = $this->handle($bundle, $modelData);

        $command->info("Bundle [{$bundle->id}] updated successfully.");
    }
}
