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

class UpdateBundle extends OrgAction
{
    use WithNoStrictRules;

    private Customer $customer;

    /**
     * @throws \Throwable
     */
    public function handle(Bundle $bundle, array $modelData): Bundle
    {
        return DB::transaction(function () use ($bundle, $modelData) {
            $productData = [];
            $selectedProducts = Arr::pull($modelData, 'products');

            data_set($productData, 'exclusive_for_customer_id', $customerSalesChannel->customer_id);
            data_set($productData, 'trade_units', Product::where('shop_id', $customerSalesChannel->shop_id)
                ->whereIn('id', Arr::pluck($selectedProducts, 'product_id'))
                ->get()
                ->map(function ($product) {
                    return $product->tradeUnits->map(function (TradeUnit $tradeUnit) {
                        return [
                            'id' => $tradeUnit->id,
                            'quantity' => $tradeUnit->orgStocks()->sum('quantity_available')
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
            'name'        => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'rrp'         => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'images'      => ['sometimes', 'array'],
            'images.*.id'    => ['sometimes', 'integer', 'exists:media,id'],
            'images.*.is_main' => ['sometimes', 'boolean']
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
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->customer       = $bundle->customer;
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

    public string $commandSignature = 'ds:bundle:store {customerSalesChannel}';

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = Bundle::where('id', $command->argument('bundle'))->firstOrFail();

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
