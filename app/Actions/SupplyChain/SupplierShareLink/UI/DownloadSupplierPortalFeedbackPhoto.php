<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\Helpers\Media\UI\DownloadAttachment;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Models\Helpers\Media;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A photo of a delivery feedback, to view or download from the supplier page. Only the photos of a sent round
 * addressed to the supplier, or of notes on its own lines, can be reached through its link.
 */
class DownloadSupplierPortalFeedbackPhoto
{
    use AsAction;

    public function handle(Supplier $supplier, Media $media, bool $inline = false): BinaryFileResponse
    {
        abort_unless($this->isFeedbackPhoto($supplier, $media), 404);

        return DownloadAttachment::make()->handle($media, $inline);
    }

    public function isFeedbackPhoto(Supplier $supplier, Media $media): bool
    {
        $attachments = fn () => DB::table('model_has_attachments')
            ->where('model_has_attachments.model_type', 'StockDelivery')
            ->where('model_has_attachments.scope', PurchaseOrderAttachmentScopeEnum::FEEDBACK->value)
            ->where('model_has_attachments.media_id', $media->id);

        return $attachments()
            ->join('stock_delivery_feedbacks', fn ($join) => $join->on('stock_delivery_feedbacks.stock_delivery_id', 'model_has_attachments.model_id')
                ->whereRaw("model_has_attachments.sub_scope = 'feedback:' || stock_delivery_feedbacks.id"))
            ->where('stock_delivery_feedbacks.supplier_id', $supplier->id)
            ->whereNotNull('stock_delivery_feedbacks.sent_at')
            ->exists()
            || $attachments()
                ->join('stock_delivery_item_feedbacks', fn ($join) => $join->on('stock_delivery_item_feedbacks.stock_delivery_id', 'model_has_attachments.model_id')
                    ->whereRaw("model_has_attachments.sub_scope = 'feedback_item:' || stock_delivery_item_feedbacks.id"))
                ->join('stock_delivery_feedbacks', 'stock_delivery_feedbacks.id', 'stock_delivery_item_feedbacks.stock_delivery_feedback_id')
                ->where('stock_delivery_item_feedbacks.supplier_id', $supplier->id)
                ->whereNotNull('stock_delivery_feedbacks.sent_at')
                ->exists();
    }

    public function asController(string $token, Media $media, Request $request): BinaryFileResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        return $this->handle($link->supplier, $media, $request->boolean('inline'));
    }
}
