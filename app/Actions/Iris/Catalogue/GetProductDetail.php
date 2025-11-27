<?php

/*
 * author Louis Perez
 * created on 20-11-2025-15h-21m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Iris\Catalogue;

use App\Actions\IrisAction;
use App\Http\Resources\Web\WebBlockProductResource;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\ActionRequest;

class GetProductDetail extends IrisAction
{
    public function handle(Product $product, array $modelData): Product
    {
        return $product;
    }

    public function jsonResponse(Product $product, ActionRequest $request): array
    {
        return WebBlockProductResource::make($product)->toArray($request);
    }

    public function asController(Product $product, ActionRequest $request): Product
    {
        $this->initialisation($request);
        return $this->handle($product, $this->validateAttributes());
    }

}
