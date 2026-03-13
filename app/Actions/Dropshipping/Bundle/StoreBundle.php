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
            data_set($productData, 'exclusive_for_customer_id', $customerSalesChannel->customer_id);
            data_set($productData, 'trade_units', Product::where('shop_id', $customerSalesChannel->shop_id)
                ->whereIn('id', Arr::get($modelData, 'products'))
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
            data_set($modelData, 'data', [
                'products' => Arr::pull($modelData, 'products')
            ]);

            /** @var Bundle $bundle */
            $bundle = $customerSalesChannel->bundles()->create($modelData);

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
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['required', 'string', 'max:64'],
            'description' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'price'       => ['required', 'numeric', 'min:0'],
            'rrp'         => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'products'    => ['required', 'array', 'min:1'],
            'products.*'  => ['required', 'integer', 'exists:products,id'],
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
            'products'    => [151812, 411847, 154425]
        ];

        $bundle = $this->handle($customerSalesChannel, $modelData);

        $command->info("Bundle [{$bundle->id}] created successfully.");
    }
}
