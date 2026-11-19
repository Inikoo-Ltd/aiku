<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\GoodsIn;

use App\Actions\GoodsIn\StockDeliveryFollowUp\StoreStockDeliveryItemFeedback;
use App\Actions\GoodsIn\StockDeliveryFollowUp\UI\GetStockDeliveryFeedbackForm;
use App\Enums\GoodsIn\StockDeliveryFollowUp\StockDeliveryFeedbackTopicEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemDiscrepancyEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemDiscrepancyOutcomeEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteStateEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteVisibilityEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\GoodsIn\StockDeliveryFeedback;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\GoodsIn\StockDeliveryItemFeedback;
use App\Models\Procurement\ProcurementNote;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The feedback round card, the same on the delivery page, for the agent and on the supplier portal. Never carries
 * money. On the supplier's own link the photos open through that link, as the staff media route needs a login, and
 * a supplier sees the general part only when it is addressed to it and the notes only on its own lines
 * (forSupplier).
 */
class StockDeliveryFeedbackResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var StockDeliveryFeedback $feedback */
        $feedback      = $this->resource;
        $stockDelivery = $feedback->stockDelivery;
        $forSupplier   = $request->routeIs('grp.supplier_share.*');
        $token         = $forSupplier ? $request->route('token') : null;

        return [
            'id'                 => $feedback->id,
            'sent_at'            => $feedback->sent_at,
            'rating'             => $feedback->rating,
            'rating_description' => $feedback->rating ? (GetStockDeliveryFeedbackForm::ratingDescriptions()[$feedback->rating] ?? null) : null,
            'topics'             => collect($feedback->topics)->map(fn (string $topic) => ['value' => $topic, 'label' => StockDeliveryFeedbackTopicEnum::tryFrom($topic)?->label() ?? $topic])->all(),
            'comment'            => $feedback->comment,
            'stock_delivery'     => [
                'reference' => $stockDelivery->reference,
                'date'      => $stockDelivery->date?->toDateString(),
            ],
            'organisation'       => $feedback->organisation->name,
            'recipient'          => self::recipient($feedback),
            'supplier'           => $feedback->supplier ? ['id' => $feedback->supplier->id, 'code' => $feedback->supplier->code, 'name' => $feedback->supplier->name] : null,
            'agent'              => $feedback->agent ? ['code' => $feedback->agent->code, 'name' => $feedback->agent->name] : null,
            'purchase_orders'    => self::purchaseOrders($feedback),
            'author'             => $forSupplier ? null : ($feedback->author?->contact_name ?? $feedback->author?->username),
            'author_id'          => $forSupplier ? null : $feedback->author_id,
            'created_at'         => $feedback->sent_at ?? $feedback->created_at,
            'photos'             => self::photos($feedback->stock_delivery_id, 'feedback:'.$feedback->id, $token),
            'instruction'        => self::instruction($feedback->procurementNote, $forSupplier),
            'items'              => $feedback->items->map(fn (StockDeliveryItemFeedback $note) => self::itemNote($note, $forSupplier, $token))->values()->all(),
        ];
    }

    /**
     * Cards for one supplier's portal: only sent rounds, the general part only when addressed to it, the notes only on
     * its own lines (the caller loads items constrained to the supplier).
     *
     * @param Collection<int, StockDeliveryFeedback> $rounds
     * @return array<int, array<string, mixed>>
     */
    public static function forSupplier(Collection $rounds, int $supplierId): array
    {
        return $rounds->map(function (StockDeliveryFeedback $round) use ($supplierId) {
            $card = self::make($round)->resolve();
            if ($round->supplier_id !== $supplierId) {
                $card = [...$card, 'rating' => null, 'rating_description' => null, 'topics' => [], 'comment' => null, 'photos' => [], 'instruction' => null, 'purchase_orders' => [], 'supplier' => null];
            }

            return $card;
        })->filter(fn (array $card) => $card['rating'] !== null || $card['items'])->values()->all();
    }

    /**
     * @return array{code: string|null, name: string|null, is_agent: bool}
     */
    public static function recipient(StockDeliveryFeedback $feedback): array
    {
        if ($feedback->supplier) {
            return ['code' => $feedback->supplier->code, 'name' => $feedback->supplier->name, 'is_agent' => false];
        }
        if ($feedback->agent) {
            return ['code' => $feedback->agent->code, 'name' => $feedback->agent->name, 'is_agent' => true];
        }

        return ['code' => $feedback->stockDelivery->parent_code, 'name' => $feedback->stockDelivery->parent_name, 'is_agent' => false];
    }

    /**
     * @return array<string, mixed>
     */
    public static function itemNote(StockDeliveryItemFeedback $note, bool $forSupplier, ?string $token): array
    {
        $item = $note->stockDeliveryItem;

        return [
            'id'                     => $note->id,
            'stock_delivery_item_id' => $note->stock_delivery_item_id,
            'code'                   => $item?->supplierProduct?->code ?? $item?->orgStock?->code,
            'org_stock_code'         => $item?->orgStock?->code,
            'name'                   => $item?->supplierProduct?->name ?? $item?->orgStock?->name,
            'supplier'               => $note->supplier ? ['id' => $note->supplier->id, 'code' => $note->supplier->code, 'name' => $note->supplier->name] : null,
            'issue'                  => $note->issue->value,
            'issue_label'            => $note->issue->label(),
            'comment'                => $note->comment,
            'author'                 => $forSupplier ? null : ($note->author?->contact_name ?? $note->author?->username),
            'author_id'              => $forSupplier ? null : $note->author_id,
            'created_at'             => $note->created_at,
            'photos'                 => self::photos($note->stock_delivery_id, StoreStockDeliveryItemFeedback::photoSubScope($note->id), $token),
            'instruction'            => self::instruction($note->procurementNote, $forSupplier),
        ];
    }

    /**
     * @return array{id: int, body: string, state: string|null}|null
     */
    private static function instruction(?ProcurementNote $note, bool $forSupplier): ?array
    {
        if (!$note || ($forSupplier && ($note->visibility !== ProcurementNoteVisibilityEnum::SUPPLIER || $note->state !== ProcurementNoteStateEnum::ACTIVE))) {
            return null;
        }

        return [
            'id'    => $note->id,
            'body'  => $note->body,
            'state' => $forSupplier ? null : $note->state->value,
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
     * @return array<int, array{media_ulid: string, caption: string|null, file_type: string|null, url: string}>
     */
    public static function photos(int $stockDeliveryId, string $subScope, ?string $token = null): array
    {
        return DB::table('model_has_attachments')
            ->join('media', 'media.id', 'model_has_attachments.media_id')
            ->where('model_has_attachments.model_type', 'StockDelivery')
            ->where('model_has_attachments.model_id', $stockDeliveryId)
            ->where('model_has_attachments.scope', PurchaseOrderAttachmentScopeEnum::FEEDBACK->value)
            ->where('model_has_attachments.sub_scope', $subScope)
            ->get(['media.ulid', 'media.file_name', 'model_has_attachments.caption'])
            ->map(fn ($media) => [
                'media_ulid'     => $media->ulid,
                'caption'        => $media->caption,
                'file_type'      => $media->file_name ? strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION)) : null,
                'url'            => $token === null
                    ? route('grp.media.download', ['media' => $media->ulid, 'inline' => true])
                    : route('grp.supplier_share.feedback_photo', ['token' => $token, 'media' => $media->ulid, 'inline' => true]),
            ])->all();
    }
}
