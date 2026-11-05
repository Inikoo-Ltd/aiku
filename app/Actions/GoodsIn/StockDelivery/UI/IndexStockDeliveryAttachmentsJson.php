<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-05 10:12
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Actions\OrgAction;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * The files attached to a shipment, fetched when the attachment count on a purchase order's stages is clicked, so the
 * purchase order page does not load them up front.
 */
class IndexStockDeliveryAttachmentsJson extends OrgAction
{
    private StockDelivery $stockDelivery;

    public function handle(StockDelivery $stockDelivery): Collection
    {
        $scopeLabels = PurchaseOrderAttachmentScopeEnum::labels();

        return DB::table('model_has_attachments')
            ->join('media', 'media.id', 'model_has_attachments.media_id')
            ->where('model_has_attachments.model_type', 'StockDelivery')
            ->where('model_has_attachments.model_id', $stockDelivery->id)
            ->orderBy('model_has_attachments.id')
            ->get([
                'model_has_attachments.id',
                'model_has_attachments.caption',
                'model_has_attachments.scope',
                'media.ulid as media_ulid',
                'media.file_name',
                'media.size',
            ])
            ->map(fn (object $attachment) => [
                'id'          => $attachment->id,
                'caption'     => $attachment->caption,
                'scope'       => $attachment->scope,
                'scope_label' => $scopeLabels[$attachment->scope] ?? $attachment->scope,
                'file_name'   => $attachment->file_name,
                'file_type'   => $attachment->file_name ? strtolower(pathinfo($attachment->file_name, PATHINFO_EXTENSION)) : null,
                'size'        => $attachment->size === null ? null : (int) $attachment->size,
                'url'         => route('grp.media.download', ['media' => $attachment->media_ulid, 'inline' => 1]),
            ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($request->user()->authTo("procurement.{$this->stockDelivery->organisation_id}.view")) {
            return true;
        }

        $agentOrganisationId = $this->stockDelivery->parent_type === 'OrgAgent' ? $this->stockDelivery->agent?->organisation_id : null;

        return $agentOrganisationId !== null && $request->user()->authTo("procurement.$agentOrganisationId.view");
    }

    public function jsonResponse(Collection $attachments): array
    {
        return ['data' => $attachments->values()->all()];
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): Collection
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery);
    }
}
