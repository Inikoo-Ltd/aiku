<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 19:36:35 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Bundle;

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Bundle;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Goods\TradeUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Faker\Factory as Faker;

class StoreBundle extends OrgAction
{
    use WithNoStrictRules;

    private Customer $customer;

    /**
     * @throws \Throwable
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): Bundle
    {
        return DB::transaction(function () use ($customerSalesChannel, $modelData) {
            $productData = [];
            $selectedProducts = Arr::pull($modelData, 'products');
            $shopBundleDiscount = Arr::get($customerSalesChannel->shop->settings, 'discount.bundle_discount_percentage', 10);

            $productSelected = Product::where('shop_id', $customerSalesChannel->shop_id)
                ->whereIn('id', Arr::pluck($selectedProducts, 'product_id'))
                ->get();

            data_set($productData, 'exclusive_for_customer_id', $customerSalesChannel->customer_id);
            data_set($productData, 'trade_units',
                $productSelected->map(function ($product) use ($selectedProducts) {
                    return $product->tradeUnits->map(function (TradeUnit $tradeUnit) use ($product, $selectedProducts) {
                        /** @var array $productQty */
                        $productQty = collect($selectedProducts)->where('product_id', $product->id)->first();

                        return [
                            'id' => $tradeUnit->id,
                            'quantity' => Arr::get($productQty, 'quantity')
                        ];
                    });
                })->collapse()->toArray());

            data_set($productData, 'description', Arr::pull($modelData, 'description'));
            data_set($productData, 'price', Arr::pull($modelData, 'price'));
            data_set($productData, 'rrp', Arr::pull($modelData, 'rrp'));
            data_set($productData, 'code', Arr::pull($modelData, 'code'));
            data_set($productData, 'name', Arr::pull($modelData, 'name'));
            data_set($productData, 'is_bundle', true);
            data_set($productData, 'is_main', true);
            data_set($productData, 'unit', 'BUNDLE');

            if(! Arr::get($productData, 'price')) {
                $productPrice = $productSelected->sum('price');
                data_set($productData, 'price', $productPrice * (1 - ($shopBundleDiscount / 100)));
            }

            if(! Arr::get($productData, 'rrp')) {
                $productRrp = $productSelected->sum('rrp');
                data_set($productData, 'rrp', $productRrp * (1 - ($shopBundleDiscount / 100)));
            }

            if(! Arr::get($productData, 'code')) {
                $productData['code'] = 'B-'.$customerSalesChannel->id.'-'.rand(1000, 9999);
            }

            if(! Arr::get($productData, 'name')) {
                $productData['name'] = $productData['code'];
            }

            if(! Arr::get($productData, 'description')) {
                $productData['description'] = $productSelected->first()->description;
            }

            $product = StoreProduct::make()->action($customerSalesChannel->shop, $productData);

            data_set($modelData, 'group_id', $customerSalesChannel->group_id);
            data_set($modelData, 'organisation_id', $customerSalesChannel->organisation_id);
            data_set($modelData, 'customer_id', $customerSalesChannel->customer_id);
            data_set($modelData, 'bundleable_id', $product->id);
            data_set($modelData, 'bundleable_type', $product->getMorphClass());
            data_set($modelData, 'status', true);
            data_set($modelData, 'data', [
                'products' => $selectedProducts
            ]);

            /** @var Bundle $bundle */
            $bundle = $customerSalesChannel->bundles()->create($modelData);

            foreach ($selectedProducts as $selectedProduct) {
                $bundle->items()->create([
                    'item_id' => Arr::get($selectedProduct, 'product_id'),
                    'item_type' => class_basename(Product::class),
                    'quantity' => Arr::get($selectedProduct, 'quantity')
                ]);
            }

            return $bundle;
        });
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
            'name'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'max:64'],
            'description' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'price'       => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'rrp'         => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'products'    => ['required', 'array', 'min:1'],
            'products.*.product_id'  => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity'  => ['required', 'numeric', 'min:1'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(CustomerSalesChannel $customerSalesChannel, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Bundle
    {
        if (!$audit) {
            Portfolio::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->customer       = $customerSalesChannel->customer;
        $this->initialisationFromShop($customerSalesChannel->shop, $modelData);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): Bundle
    {
        $this->customer = $customerSalesChannel->customer;

        $this->initialisationFromShop($customerSalesChannel->shop, $request);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }

    public string $commandSignature = 'ds:bundle:store {customerSalesChannel}';

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        $faker = Faker::create();
        $modelData = [
            'name'        => $faker->name,
            'code'        => $faker->bothify('B-####'),
            'price'       => $faker->randomFloat(2, 10, 1000),
            'rrp'         => $faker->randomFloat(2, 10, 1000),
            'description' => $faker->sentence(),
            'products'    => [
                ['product_id' => 151812, 'quantity' => 1],
                ['product_id' => 411847, 'quantity' => 2],
                ['product_id' => 154425, 'quantity' => 3]
            ]
        ];

        $bundle = $this->handle($customerSalesChannel, $modelData);

        $command->info("Bundle [{$bundle->id}] created successfully.");
    }
}
