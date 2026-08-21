<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 May 2025 16:07:56 Central Indonesia Time, Sanur, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Web\WebBlock\Concerns\HasWebBlockLayoutData;
use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Http\Resources\Helpers\Attachment\IrisAttachmentsResource;
use App\Http\Resources\Web\WebBlockFamilyResource;
use App\Http\Resources\Web\WebBlockProductResource;
use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Models\Catalogue\Variant;
use Illuminate\Support\Arr;

class GetWebBlockProduct
{
    use AsObject;
    use HasWebBlockLayoutData;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        /** @var Product $product */
        $product = $webpage->model;

        if (!$product->is_for_sale && !($product->is_variant_leader)) {
            abort(404);
        }

        $attachments = DB::table('media')
            ->join('model_has_attachments', function ($join) use ($webpage) {
                $join->on('model_has_attachments.media_id', '=', 'media.id')
                    ->where('model_has_attachments.model_type', '=', 'Product')
                    ->where('model_has_attachments.model_id', $webpage->model_id);
            })
            ->select(['model_has_attachments.caption', 'model_has_attachments.scope', 'model_has_attachments.media_id', 'media.ulid as media_ulid', 'media.mime_type as mime_type'])
            ->whereIn('model_has_attachments.scope', [
                TradeAttachmentScopeEnum::ALLERGEN_DECLARATIONS,
                TradeAttachmentScopeEnum::CPSR,
                TradeAttachmentScopeEnum::DOC,
                TradeAttachmentScopeEnum::IFRA,
                TradeAttachmentScopeEnum::SDS,
                TradeAttachmentScopeEnum::TEST_REPORTS,
            ])
            ->get();

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
        data_set($webBlock, 'web_block.layout.data.fieldValue.product.attachments', IrisAttachmentsResource::collection($attachments)->resolve());

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
