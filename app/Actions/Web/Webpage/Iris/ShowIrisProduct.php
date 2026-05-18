<?php

namespace App\Actions\Web\Webpage\Iris;

use App\Actions\IrisAction;
use App\Http\Resources\Catalogue\ProductResource;
use App\Models\Catalogue\Product;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowIrisProduct extends IrisAction
{
    public function handle(Product $product): Product
    {
        return $product;
    }

    public function asController(Product $product, ActionRequest $request): Product
    {
        $this->initialisation($request);

        return $this->handle($product);
    }

    public function htmlResponse(Product $product, ActionRequest $request): Response
    {
        return Inertia::render(
            'Catalogue/Product',
            [
                'catalogue_scope' => 'product',
                'title'           => $product->name,
                'pageHead'        => [
                    'title' => $product->name,
                    'model' => __('Product'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-cube'],
                        'title' => __('Product'),
                    ],
                    'iconRight' => $product->state->stateIcon()[$product->state->value],
                ],

                'data' => [
                    'product'       => ProductResource::make($product)->resolve(),
                    'data_feed_url' => route('iris.catalogue.feeds.product.download', ['product' => $product->slug]),
                ],
            ]
        );
    }
}
