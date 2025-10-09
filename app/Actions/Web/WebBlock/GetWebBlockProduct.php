<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 May 2025 16:07:56 Central Indonesia Time, Sanur, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Http\Resources\Helpers\Attachment\IrisAttachmentsResource;
use App\Http\Resources\Web\WebBlockProductResource;
use App\Http\Resources\Web\WebBlockProductResourceEcom;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockProduct
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {

        $permissions =  [];
        $attachments = DB::table('media')
            ->join('model_has_attachments', function ($join) use ($webpage) {
                $join->on('model_has_attachments.media_id', '=', 'media.id')
                    ->where('model_has_attachments.model_type', '=', 'Product')
                    ->where('model_has_attachments.model_id', $webpage->model_id);
            })
            ->select(['model_has_attachments.caption','model_has_attachments.scope', 'model_has_attachments.media_id', 'media.ulid as media_ulid'])
            ->whereIn('model_has_attachments.scope', [TradeAttachmentScopeEnum::ALLERGEN_DECLARATIONS, TradeAttachmentScopeEnum::CPSR, TradeAttachmentScopeEnum::DOC, TradeAttachmentScopeEnum::IFRA, TradeAttachmentScopeEnum::SDS])
            ->get();

        if ($webpage->shop->type == ShopTypeEnum::B2B) {
            $resourceWebBlockProduct = WebBlockProductResourceEcom::make($webpage->model)->toArray(request());
        } else {
            $resourceWebBlockProduct = WebBlockProductResource::make($webpage->model)->toArray(request());
        }

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue', $webpage->website->published_layout['product']['data']['fieldValue'] ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.product', $resourceWebBlockProduct);
        data_set($webBlock, 'web_block.layout.data.fieldValue.product.attachments', IrisAttachmentsResource::collection($attachments)->resolve());

        return $webBlock;
    }

}
