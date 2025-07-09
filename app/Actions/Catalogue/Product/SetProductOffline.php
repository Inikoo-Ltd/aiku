<?php
/*
 * author Arya Permana - Kirin
 * created on 09-07-2025-18h-05m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class SetProductOffline extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Product $product, array $modelData)
    {
        dd('TODO');

    }

    public function asController(Product $product, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($product->shop, $request);

        return $this->handle($product, $this->validatedData);
    }
}
