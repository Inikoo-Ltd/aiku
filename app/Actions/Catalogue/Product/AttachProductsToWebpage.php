<?php
/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-11h-40m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Enums\Web\Webpage\WebpageHasProductTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class AttachProductsToWebpage extends OrgAction
{
    public function handle(Webpage $webpage, array $modelData)
    {
        $productIds = Arr::get($modelData, 'products', []);

        foreach ($productIds as $productId) {
            $product = Product::find($productId);
            if ($product) {
                AttachProductToWebpage::make()->action($webpage, $product, [
                    'type' => WebpageHasProductTypeEnum::DIRECT
                ]);
            }
        }

        return true;
    }

    public function rules(): array
    {
        return [
                'products'   => ['nullable', 'array'],
                'products.*' => ['exists:products,id'],
        ];
    }

    public function asController(Webpage $webpage, ActionRequest $request)
    {
        $this->initialisationFromShop($webpage->shop, $request);
        $this->handle($webpage, $this->validatedData);
    }
}
