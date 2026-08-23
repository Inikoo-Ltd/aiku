<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Find products in a shop by code, name or barcode and return their price, RRP, status, stock available, family, department, units, images and web page URL. Use this to answer "tell me about product X" or "what does SKU X cost / is it in stock".')]
#[IsReadOnly]
class ProductLookupTool extends AikuTool
{
    protected function permission(): ShopPermissionsEnum
    {
        return ShopPermissionsEnum::PRODUCTS_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop'  => ['required', 'string'],
            'query' => ['required', 'string', 'min:2'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $shop = $this->authorisedShop($request);
        if (!$shop) {
            return $this->shopNotFoundError($request);
        }

        $query = (string) $request->string('query');
        $like  = '%'.$query.'%';

        $products = Product::where('shop_id', $shop->id)
            ->where('is_main', true)
            ->with(['family:id,code,name', 'department:id,code,name', 'webpage:id,model_type,model_id,url,website_id', 'webpage.website:id,domain', 'stats:product_id,number_public_images'])
            ->where(function ($q) use ($query, $like) {
                $q->whereRaw('lower(code) = ?', [strtolower($query)])
                    ->orWhere('code', 'ilike', $like)
                    ->orWhere('name', 'ilike', $like)
                    ->orWhere('barcode', $query);
            })
            ->orderByRaw('lower(code) = ? desc', [strtolower($query)])
            ->orderBy('code')
            ->limit($request->integer('limit', 10))
            ->get();

        return Response::json([
            'shop'     => $shop->name,
            'currency' => $shop->currency->code,
            'results'  => $products->map(fn (Product $product) => [
                'code'                 => $product->code,
                'name'                 => $product->name,
                'state'                => $product->state?->value,
                'status'               => $product->status?->value,
                'is_for_sale'          => $product->is_for_sale,
                'price'                => $product->price,
                'rrp'                  => $product->rrp,
                'units'                => $product->units,
                'unit'                 => $product->unit,
                'barcode'              => $product->barcode,
                'available_quantity'   => $product->available_quantity,
                'out_of_stock_since'   => $product->out_of_stock_since,
                'family'               => $product->family ? $product->family->code.' ('.$product->family->name.')' : null,
                'department'           => $product->department ? $product->department->code.' ('.$product->department->name.')' : null,
                'number_public_images' => $product->stats?->number_public_images ?? 0,
                'is_in_website'        => $product->is_in_website,
                'url'                  => $product->webpage?->website ? $product->webpage->getUrl() : null,
            ])->values(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop'  => $schema->string()->description('Shop slug or code, e.g. eu or EU')->required(),
            'query' => $schema->string()->description('Product code, barcode, or text matched against code and name')->required(),
            'limit' => $schema->integer()->description('Maximum products to return, default 10')->min(1)->max(50),
        ];
    }
}
