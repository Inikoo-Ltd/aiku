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
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Goods\TradeUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class CalculateBundleItemPriceDetails extends OrgAction
{
    use WithNoStrictRules;

    private Customer $customer;

    /**
     * @throws \Throwable
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): array
    {
        return DB::transaction(function () use ($customerSalesChannel, $modelData) {
            $shop = $customerSalesChannel->shop;
            $productData = [];

            $selectedProducts = Arr::pull($modelData, 'products');
            $bundleDiscount = Arr::get($shop->settings, 'discount.bundle_discount_percentage');

            foreach ($selectedProducts as $selectedProduct) {
                $quantity = Arr::get($selectedProduct, 'quantity');
                $product = Product::find($selectedProduct['product_id']);

                $individualPrice = $product->price * $quantity;
                $individualRrpPrice = $product->rrp * $quantity;

                $productData[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $individualPrice,
                    'rrp' => $individualRrpPrice,
                    'bundle_price' => $individualPrice * (1 - ($bundleDiscount / 100)),
                ];
            }

            $totalPrice = collect($productData)->sum('price');
            $totalBundlePrice = collect($productData)->sum('bundle_price');
            $totalRrp = collect($productData)->sum('rrp');

            $profit = $totalRrp - $totalBundlePrice;

            return [
                'products' => $productData,
                'total_price' => $totalPrice,
                'total_bundle_price' => $totalBundlePrice,
                'total_rrp' => $totalRrp,
                'profit' => $profit,
                'profit_percentage' => round($totalRrp > 0
                    ? ($profit / $totalRrp) * 100
                    : 0),
            ];
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
        return [
            'products.*.product_id'  => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity'  => ['required', 'numeric', 'min:1'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): array
    {
        $this->initialisationFromShop($this->customer->shop, $request);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }

    public string $commandSignature = 'ds:bundle:product:calculate';

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        $modelData = [
            'products'    => [
                ['product_id' => 151812, 'quantity' => 1],
                ['product_id' => 411847, 'quantity' => 2],
                ['product_id' => 154425, 'quantity' => 3]
            ]
        ];

        $bundle = $this->handle($customerSalesChannel, $modelData);
        dd($bundle);
    }
}
