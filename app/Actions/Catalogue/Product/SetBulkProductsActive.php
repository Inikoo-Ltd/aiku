<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Bali Office, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class SetBulkProductsActive extends OrgAction
{
    use WithCatalogueEditAuthorisation;

    /**
     * @param  array{products: array<int, int>}  $modelData
     */
    public function handle(Shop $shop, array $modelData): int
    {
        $productsToActivate = Product::where('shop_id', $shop->id)
            ->whereIn('id', $modelData['products'])
            ->where('state', ProductStateEnum::IN_PROCESS)
            ->get();

        foreach ($productsToActivate as $product) {
            UpdateProduct::make()->action($product, [
                'state' => ProductStateEnum::ACTIVE,
            ]);
        }

        return $productsToActivate->count();
    }

    public function rules(): array
    {
        return [
            'products'   => ['required', 'array', 'min:1'],
            'products.*' => ['required', 'integer'],
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function asController(Shop $shop, ActionRequest $request): int
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function action(Shop $shop, array $modelData): int
    {
        $this->asAction = true;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }
}
