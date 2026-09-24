<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 19:36:35 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Bundle;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Product\UpdateProductImages;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateBundles;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Dropshipping\Portfolio\WithPortfolioSKU;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Portfolio\UpdateAndUploadRetinaPortfolioToCurrentChannel;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Actions\Traits\WithOpenCustomerSalesChannelCheck;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Bundle;
use App\Models\Dropshipping\BundleItem;
use App\Models\Dropshipping\Portfolio;
use App\Models\Helpers\Media;
use Faker\Factory as Faker;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateBundle extends OrgAction
{
    use WithNoStrictRules;
    use WithActionUpdate;
    use WithAttachMediaToModel;
    use WithOpenCustomerSalesChannelCheck;
    use WithBundleTradeUnits;
    use WithPortfolioSKU;

    private Customer $customer;

    /**
     * @throws \Throwable
     */
    public function handle(Bundle $bundle, array $modelData): Bundle
    {
        $this->assertCustomerSalesChannelIsOpen($bundle->customerSalesChannel);

        return DB::transaction(function () use ($bundle, $modelData) {
            Arr::forget($modelData, 'id');

            /** @var Product $product */
            $product = $bundle->bundleable;
            $portfolio = Portfolio::where('bundle_id', $bundle->id)->first();
            $shopBundleDiscount = Arr::get($product->shop->settings, 'discount.bundle_discount_percentage', 10);

            $this->update($product, Arr::only($modelData, ['name', 'description', 'rrp']));

            $portfolioData = $this->getPortfolioPresentationData($modelData);

            /** @var array $mainMedia */
            $mainMedia = collect(Arr::get($modelData, 'images'))->where('is_main', true)->first();
            $images = collect(Arr::get($modelData, 'images'))->pluck('id');

            $selectedProducts = [];
            if (Arr::get($modelData, 'products')) {
                $selectedProducts = $this->processDelete($bundle, Arr::get($modelData, 'products'));
            }

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

            $productPrice = null;
            $productRrp   = null;

            if (Arr::get($modelData, 'payloadItems')) {
                $selectedBundleItems = Arr::get($modelData, 'payloadItems');

                foreach ($selectedBundleItems as $selectedBundleItem) {
                    $bundleItem = BundleItem::find($selectedBundleItem['bundle_item_id']);

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
                $productRrp = Arr::get($modelData, 'rrp') ?? $productRrp;
            }

            if (! blank($selectedProducts)) {
                foreach ($selectedProducts as $selectedProduct) {
                    $bundleItem = BundleItem::where('bundle_id', $bundle->id)
                        ->where('item_type', class_basename(Product::class))
                        ->where('item_id', $selectedProduct['product_id'])
                        ->first();

                    if ($bundleItem) {
                        $this->update($bundleItem, [
                            'quantity' => Arr::get($selectedProduct, 'quantity')
                        ]);

                        continue;
                    }

                    $bundle->items()->create([
                        'item_id' => Arr::get($selectedProduct, 'product_id'),
                        'item_type' => class_basename(Product::class),
                        'quantity' => Arr::get($selectedProduct, 'quantity')
                    ]);
                }

                $calculatedPrice = CalculateBundleItemPriceDetails::run($bundle->customerSalesChannel, $modelData);
                $productPrice    = Arr::get($calculatedPrice, 'total_price');
                $productRrp      = Arr::get($modelData, 'rrp') ?? Arr::get($calculatedPrice, 'total_rrp');
            }

            /* The trade units are read back off the bundle once every item has been created,
               requantified or deleted, so what the product carries is the whole of what the bundle
               now holds rather than whichever slice this request happened to mention. */
            if ($productRrp !== null) {
                UpdateProduct::make()->action($product, [
                    'trade_units' => $this->getCurrentBundleTradeUnits($bundle),
                    'price'       => $productPrice,
                    'rrp'         => $productRrp
                ]);

                data_set($portfolioData, 'selling_price', $productRrp);
                data_set($portfolioData, 'customer_price', $productRrp);
                data_set($portfolioData, 'sku', $this->getSKU($product->refresh()));
            }

            if ($portfolio && $portfolioData) {
                UpdatePortfolio::make()->action($portfolio, $portfolioData);
                UpdateAndUploadRetinaPortfolioToCurrentChannel::run($portfolio, []);
            }

            $bundle->refresh();

            ShopHydrateBundles::dispatch($bundle->customer->shop)->delay($this->hydratorsDelay);

            return $bundle;
        });
    }

    /**
     * @return array<int, array{id: int, quantity: float}>
     */
    private function getCurrentBundleTradeUnits(Bundle $bundle): array
    {
        $bundleItems = $bundle->items()
            ->where('item_type', class_basename(Product::class))
            ->get();

        $componentProducts = Product::whereIn('id', $bundleItems->pluck('item_id'))->get();

        $componentQuantities = $bundleItems->map(fn (BundleItem $bundleItem) => [
            'product_id' => $bundleItem->item_id,
            'quantity'   => $bundleItem->quantity
        ])->all();

        return $this->getBundleTradeUnits($componentProducts, $componentQuantities);
    }

    private function getPortfolioPresentationData(array $modelData): array
    {
        $portfolioData = [];

        if (Arr::exists($modelData, 'name')) {
            data_set($portfolioData, 'customer_product_name', Arr::get($modelData, 'name'));
        }

        if (Arr::exists($modelData, 'description')) {
            data_set($portfolioData, 'customer_description', Arr::get($modelData, 'description'));
        }

        return $portfolioData;
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
            'description' => ['sometimes', 'nullable', 'string', 'max:15000'],
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
