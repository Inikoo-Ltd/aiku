<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 May 2025 16:07:56 Central Indonesia Time, Sanur, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Web\WebBlock\Concerns\HasWebBlockLayoutData;
use App\Actions\Web\WebBlock\Concerns\HasWebBlockProductAttachments;
use App\Actions\Web\WebBlock\Concerns\HasWebBlockProductLabelInfo;
use App\Http\Resources\Web\WebBlockFamilyResource;
use App\Http\Resources\Web\WebBlockProductResource;
use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Models\Catalogue\Variant;
use Illuminate\Support\Arr;

class GetWebBlockProduct
{
    use AsObject;
    use HasWebBlockLayoutData;
    use HasWebBlockProductAttachments;
    use HasWebBlockProductLabelInfo;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        /** @var Product $product */
        $product = $webpage->model;

        if (!$product->is_for_sale && !($product->is_variant_leader)) {
            abort(404);
        }


        $variant     = $product->is_variant_leader ? Variant::where('leader_id', $product->id)->first() : null;

        $resourceWebBlockProduct = WebBlockProductResource::make($webpage->model)->toArray(request());

        $webPublishedLayout = $webpage->website->published_layout;

        $tabs = [
            'description'       => $product->description,
            'marketing_material_route'  => [
                'name'          => 'iris.catalogue.feeds.product.download_img',
                'parameters'    => [
                    'product'   => $product->slug,
                ]
            ],
            ...Arr::except(($product?->family ? WebBlockFamilyResource::getTabsData($product->family) : []), 'marketing_material_route'),
        ];

        data_set($webBlock, 'web_block.layout.data.fieldValue', data_get($webPublishedLayout, 'product.data.fieldValue', []));
        data_set($webBlock, 'web_block.layout.data.fieldValue.tabs', $tabs);
        data_set($webBlock, 'web_block.layout.data.fieldValue.tabs_style', $this->getFamilyExtraDescriptionLayoutData($webPublishedLayout));
        data_set($webBlock, 'web_block.layout.data.fieldValue.product', $resourceWebBlockProduct);
        data_set($webBlock, 'web_block.layout.data.fieldValue.product.attachments', $this->getProductAttachments($product->id));
        data_set($webBlock, 'web_block.layout.data.fieldValue.product.label_info', $this->getProductLabelInfo($product));
        data_set($webBlock, 'web_block.layout.data.fieldValue.product.is_label_info_approved', $this->isProductLabelInfoApproved($product));

        if ($variant) {
            $variant = $variant->only(['id', 'data']);
            $excludedProducts = collect(data_get($variant, 'data.products'))->reject(fn ($product) => isset($product['is_hide']) ? $product['is_hide'] : false);

            data_set($variant, 'data.products', $excludedProducts);
            data_set($webBlock, 'web_block.layout.data.fieldValue.variant', $variant);
        }


        return [
           'type' => data_get($webBlock, 'type'),
           'structure' => data_get(
               $webBlock,
               'web_block.layout.data.fieldValue',
               []
           ),
        ];
    }
}
