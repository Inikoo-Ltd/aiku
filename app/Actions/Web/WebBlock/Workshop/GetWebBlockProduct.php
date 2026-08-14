<?php

/*
 * author Louis Perez
 * created on 28-05-2026-14h-27m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Http\Resources\Helpers\Attachment\IrisAttachmentsResource;
use App\Http\Resources\Web\WebBlockProductForWorkshopResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Variant;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockProduct
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        /** @var Product $product */
        $product = $webpage->model;

        $permissions = [];
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
            
        $variant            = null;
        $isNaturalVariant   = false;
        if ($product->show_siblings_as_option) {
            $siblings = $product->family->getActiveProducts()->orderBy('code');

            $variant = [
                'id'    => null,
                'data'  => [
                    'groupBy'   => 'Siblings',
                    'products'  => $siblings->mapWithKeys(fn ($product) => [
                        $product->id    => [
                            'Siblings'  => $product->code,
                            'product'   => [
                                'id'        =>  $product->id,
                                'code'      =>  $product->code, 
                                'name'      =>  $product->name, 
                                'slug'      =>  $product->slug,
                                'images'    =>  $product->web_images
                            ],
                            'is_leader' => $product->id == $webpage->model_id, // TODO CHANGE ARYA
                        ]
                    ]),
                    'variants'  => [
                        'label'     => 'Siblings',
                        'options'   => $siblings->pluck('code')
                    ]
                ],
                'is_natural_variant'   => $isNaturalVariant
            ];
        } elseif ($product->is_variant_leader) {
            $variant            = Variant::where('leader_id', $product->id)->first()?->only(['id', 'data']);
            $isNaturalVariant   = true;
            $variant->is_natural_variant = $isNaturalVariant;
        }

        $resourceWebBlockProduct = WebBlockProductForWorkshopResource::make($webpage->model)->toArray(request());
        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'show', true);
        data_set($webBlock, 'web_block.layout.data.fieldValue', $webpage->website->published_layout['product']['data']['fieldValue'] ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.product', $resourceWebBlockProduct);
        data_set($webBlock, 'web_block.layout.data.fieldValue.product.attachments', IrisAttachmentsResource::collection($attachments)->resolve());

        if ($variant) {
            $excludedProducts = $isNaturalVariant ? collect(data_get($variant, 'data.products'))->reject(fn ($product) => isset($product['is_hide']) ? $product['is_hide'] : false) : null;

            data_set($variant, 'data.products', $excludedProducts);
            data_set($webBlock, 'web_block.layout.data.fieldValue.variant', $variant);
        }

        return $webBlock;
    }
}
