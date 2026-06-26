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

        $variant     = $product->is_variant_leader ? Variant::where('leader_id', $product->id)->first() : null;

        $resourceWebBlockProduct = WebBlockProductForWorkshopResource::make($webpage->model)->toArray(request());
        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'show', true);
        data_set($webBlock, 'web_block.layout.data.fieldValue', $webpage->website->published_layout['product']['data']['fieldValue'] ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.product', $resourceWebBlockProduct);
        data_set($webBlock, 'web_block.layout.data.fieldValue.product.attachments', IrisAttachmentsResource::collection($attachments)->resolve());

        if ($variant) {
            data_set($webBlock, 'web_block.layout.data.fieldValue.variant', $variant->only(['id', 'data']));
        }

        return $webBlock;
    }
}
