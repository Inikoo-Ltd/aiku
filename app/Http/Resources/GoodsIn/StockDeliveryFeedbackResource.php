<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\GoodsIn;

use App\Actions\GoodsIn\StockDeliveryFollowUp\UI\GetStockDeliveryFeedbackForm;
use App\Enums\GoodsIn\StockDeliveryFollowUp\StockDeliveryFeedbackTopicEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemDiscrepancyEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemDiscrepancyOutcomeEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\GoodsIn\StockDeliveryFeedback;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * A feedback card, the same on the delivery page and the supplier portal. Never carries money. Photos are left out
 * on the supplier's own link, as their download route is staff only.
 */
class StockDeliveryFeedbackResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var StockDeliveryFeedback $feedback */
        $feedback      = $this->resource;
        $stockDelivery = $feedback->stockDelivery;

        return [
            'id'                 => $feedback->id,
            'rating'             => $feedback->rating,
            'rating_description' => GetStockDeliveryFeedbackForm::ratingDescriptions()[$feedback->rating] ?? null,
            'topics'             => collect($feedback->topics)->map(fn (string $topic) => ['value' => $topic, 'label' => StockDeliveryFeedbackTopicEnum::tryFrom($topic)?->label() ?? $topic])->all(),
            'comment'            => $feedback->comment,
            'stock_delivery'     => [
                'reference' => $stockDelivery->reference,
                'date'      => $stockDelivery->date?->toDateString(),
            ],
            'organisation'       => $feedback->organisation->name,
            'supplier'           => $feedback->supplier ? ['id' => $feedback->supplier->id, 'code' => $feedback->supplier->code, 'name' => $feedback->supplier->name] : null,
            'agent'              => $feedback->agent ? ['code' => $feedback->agent->code, 'name' => $feedback->agent->name] : null,
            'purchase_orders'    => self::purchaseOrders($feedback),
            'author'             => $feedback->author?->contact_name ?? $feedback->author?->username,
            'author_id'          => $feedback->author_id,
            'created_at'         => $feedback->created_at,
            'photos'             => $request->routeIs('grp.supplier_share.*') ? [] : self::photos($feedback),
            'instruction'        => $feedback->procurementNote ? [
                'id'    => $feedback->procurementNote->id,
                'body'  => $feedback->procurementNote->body,
                'state' => $feedback->procurementNote->state->value,
            ] : null,
        ];
    }

    /**
     * The lines of this supplier on the delivery that arrived under or over and are not settled yet.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function openDiscrepancies(StockDeliveryFeedback $feedback): array
    {
        return $feedback->stockDelivery->items()
            ->whereNotNull('checked_at')
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->whereColumn('unit_quantity_checked', '!=', 'unit_quantity')
            ->when($feedback->supplier_id, fn ($query) => $query->whereIn('supplier_product_id', DB::table('supplier_products')->where('supplier_id', $feedback->supplier_id)->select('id')))
            ->with(['organisation', 'claim', 'orgStock:id,code'])
            ->get()
            ->filter(fn (StockDeliveryItem $item) => StockDeliveryItemDiscrepancyEnum::isFlagged($item->discrepancy())
                && ($item->discrepancy_outcome === null
                    || $item->discrepancy_outcome === StockDeliveryItemDiscrepancyOutcomeEnum::RECOUNT_REQUESTED
                    || ($item->claim && !$item->claim->state->isClosed())))
            ->map(fn (StockDeliveryItem $item) => [
                'id'            => $item->id,
                'code'          => $item->orgStock?->code,
                'expected'      => (float) $item->unit_quantity,
                'received'      => (float) $item->unit_quantity_checked,
                'outcome'       => $item->discrepancy_outcome?->value,
                'outcome_label' => $item->discrepancy_outcome ? StockDeliveryItemDiscrepancyOutcomeEnum::labels()[$item->discrepancy_outcome->value] : null,
                'claim_state'   => $item->claim?->state->value,
            ])->values()->all();
    }

    /**
     * @return array<int, string>
     */
    public static function purchaseOrders(StockDeliveryFeedback $feedback): array
    {
        return DB::table('purchase_orders')
            ->join('purchase_order_stock_delivery', 'purchase_order_stock_delivery.purchase_order_id', 'purchase_orders.id')
            ->where('purchase_order_stock_delivery.stock_delivery_id', $feedback->stock_delivery_id)
            ->when($feedback->supplier_id, fn ($query) => $query->whereExists(fn ($query) => $query->from('purchase_order_transactions')
                ->join('supplier_products', 'supplier_products.id', 'purchase_order_transactions.supplier_product_id')
                ->whereColumn('purchase_order_transactions.purchase_order_id', 'purchase_orders.id')
                ->where('purchase_order_transactions.state', '!=', PurchaseOrderTransactionStateEnum::CANCELLED->value)
                ->where('supplier_products.supplier_id', $feedback->supplier_id)))
            ->orderBy('purchase_orders.reference')
            ->pluck('purchase_orders.reference')
            ->all();
    }

    /**
     * @return array<int, array{media_ulid: string, caption: string|null, file_type: string|null, download_route: array<string, mixed>}>
     */
    public static function photos(StockDeliveryFeedback $feedback): array
    {
        return DB::table('model_has_attachments')
            ->join('media', 'media.id', 'model_has_attachments.media_id')
            ->where('model_has_attachments.model_type', 'StockDelivery')
            ->where('model_has_attachments.model_id', $feedback->stock_delivery_id)
            ->where('model_has_attachments.scope', PurchaseOrderAttachmentScopeEnum::FEEDBACK->value)
            ->where('model_has_attachments.sub_scope', 'feedback:'.$feedback->id)
            ->get(['media.ulid', 'media.file_name', 'model_has_attachments.caption'])
            ->map(fn ($media) => [
                'media_ulid'     => $media->ulid,
                'caption'        => $media->caption,
                'file_type'      => $media->file_name ? strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION)) : null,
                'download_route' => ['name' => 'grp.media.download', 'parameters' => [$media->ulid]],
            ])->all();
    }
}
