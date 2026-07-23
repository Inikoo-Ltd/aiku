<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Products in a shop that have no image, so content teams can fix listings.')]
class ProductsWithoutImagesTool extends AikuTool
{
    protected function permission(): ShopPermissionsEnum
    {
        return ShopPermissionsEnum::PRODUCTS_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop' => ['required', 'string'],
            'limit' => ['integer', 'min:1', 'max:100'],
        ]);

        $shop = $this->authorisedShop($request);
        if (!$shop) {
            return Response::error('Shop not found or permission denied.');
        }

        $limit = $request->integer('limit', 20);

        $query = Product::where('shop_id', $shop->id)
            ->whereNull('image_id')
            ->where('state', '!=', ProductStateEnum::DISCONTINUED);

        $total = $query->count();

        $products = $query
            ->orderBy('code')
            ->limit($limit)
            ->get(['code', 'name'])
            ->map(fn (Product $product) => [
                'code' => $product->code,
                'name' => $product->name,
            ])
            ->toArray();

        return Response::json([
            'shop' => $shop->name,
            'total_without_images' => $total,
            'products' => $products,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop' => $schema->string()->description('Shop slug')->required(),
            'limit' => $schema->integer()->description('Maximum number of products to return (default 20, max 100)'),
        ];
    }
}
