<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Sep 2026 17:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class RetireProductIntoReplacement extends OrgAction
{
    use WithCatalogueEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Product $product): Product
    {
        $replacement = Product::find(Arr::get($product->data, 'replaced_by_product_id'));
        if (!$replacement || !$replacement->is_for_sale) {
            throw ValidationException::withMessages([
                'product' => __('The replacement product is not on sale, this product cannot be retired into it'),
            ]);
        }

        $modelData = [
            'is_main'     => false,
            'is_for_sale' => false,
        ];

        if (!$this->isInCommittedOrder($product)) {
            $modelData['status'] = ProductStatusEnum::DISCONTINUED;
            $modelData['state']  = ProductStateEnum::DISCONTINUED;
        }

        return UpdateProduct::make()->action($product, $modelData);
    }

    private function isInCommittedOrder(Product $product): bool
    {
        return DB::table('transactions')
            ->join('orders', 'orders.id', 'transactions.order_id')
            ->where('transactions.model_type', 'Product')
            ->where('transactions.model_id', $product->id)
            ->whereNull('transactions.deleted_at')
            ->whereNotIn('orders.state', [
                OrderStateEnum::CREATING->value,
                OrderStateEnum::FINALISED->value,
                OrderStateEnum::DISPATCHED->value,
                OrderStateEnum::CANCELLED->value,
            ])
            ->exists();
    }

    /**
     * @throws \Throwable
     */
    public function asController(Product $product, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($product->shop, $request);
        $this->handle($product);

        return back();
    }

    /**
     * @throws \Throwable
     */
    public function action(Product $product): Product
    {
        $this->asAction = true;
        $this->initialisationFromShop($product->shop, []);

        return $this->handle($product);
    }
}
