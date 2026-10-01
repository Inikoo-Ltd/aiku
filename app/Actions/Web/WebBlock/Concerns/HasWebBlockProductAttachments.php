<?php

namespace App\Actions\Web\WebBlock\Concerns;

use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Http\Resources\Helpers\Attachment\IrisAttachmentsResource;
use Illuminate\Support\Facades\DB;

trait HasWebBlockProductAttachments
{
    protected function getProductAttachments(int $productId): array
    {
        $attachments = DB::table('media')
            ->join('model_has_attachments', function ($join) use ($productId) {
                $join->on('model_has_attachments.media_id', '=', 'media.id')
                    ->where('model_has_attachments.model_type', '=', 'Product')
                    ->where('model_has_attachments.model_id', $productId);
            })
            ->select(['model_has_attachments.caption', 'model_has_attachments.scope', 'model_has_attachments.media_id', 'media.ulid as media_ulid', 'media.mime_type as mime_type'])
            ->whereIn('model_has_attachments.scope', TradeAttachmentScopeEnum::publicScopes())
            ->get();

        return IrisAttachmentsResource::collection($attachments)->resolve();
    }
}
