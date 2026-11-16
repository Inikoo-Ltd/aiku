<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryFollowUp\UI;

use App\Actions\GoodsIn\StockDeliveryFollowUp\StoreStockDeliveryFeedback;
use App\Enums\GoodsIn\StockDeliveryFollowUp\StockDeliveryFeedbackTopicEnum;
use App\Http\Resources\GoodsIn\StockDeliveryFeedbackResource;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryFeedback;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What the delivery page needs to give and show feedback. The form offers only the suppliers on the delivery with
 * no feedback yet (a delivery with no supplier products, a partner one, is one entry), with nothing preselected.
 * No money, so the warehouse page gets it too.
 */
class GetStockDeliveryFeedbackForm
{
    use AsObject;

    /**
     * @return array<int, string>
     */
    public static function ratingDescriptions(): array
    {
        return [
            5 => __('Excellent — everything received correctly'),
            4 => __('Good — small improvement needed'),
            3 => __('Issues requiring attention'),
            2 => __('Significant problems'),
            1 => __('Serious delivery failure'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(StockDelivery $stockDelivery): array
    {
        $given     = $stockDelivery->feedbacks()->pluck('supplier_id')->map(fn ($id) => (int) $id);
        $suppliers = StoreStockDeliveryFeedback::suppliersOn($stockDelivery);

        $entries = $suppliers->isEmpty()
            ? collect($given->isEmpty() ? [['id' => null, 'code' => $stockDelivery->parent_code, 'name' => $stockDelivery->parent_name]] : [])
            : $suppliers->reject(fn (Supplier $supplier) => $given->contains($supplier->id))
                ->map(fn (Supplier $supplier) => ['id' => $supplier->id, 'code' => $supplier->code, 'name' => $supplier->name]);

        return [
            'suppliers'           => $entries->map(fn (array $entry) => [
                ...$entry,
                'purchase_orders' => StockDeliveryFeedbackResource::purchaseOrders(new StockDeliveryFeedback(['stock_delivery_id' => $stockDelivery->id, 'supplier_id' => $entry['id']])),
            ])->values()->all(),
            'rating_descriptions' => collect(self::ratingDescriptions())->map(fn (string $label, int $rating) => ['value' => $rating, 'label' => $label])->values()->all(),
            'topics'              => collect(StockDeliveryFeedbackTopicEnum::labels())->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'default_comments'    => [
                5 => __('Thank you. Goods received in perfect condition, quantities correct and packaging excellent.'),
            ],
            'placeholders'        => [
                4 => __('Thank you. Goods received in good condition. Please put carton labels on the side of the carton, rather than underneath.'),
            ],
            'max_photos'          => 10,
            'store_route'         => [
                'name'       => 'grp.models.stock-delivery.feedback.store',
                'parameters' => ['stockDelivery' => $stockDelivery->id],
                'method'     => 'post',
            ],
        ];
    }

    /**
     * One card per feedback, each with the open discrepancies of its supplier on the delivery and the actions the
     * user may take on it.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function cards(StockDelivery $stockDelivery, User $user, bool $canEdit): array
    {
        return $stockDelivery->feedbacks()
            ->with(['supplier', 'agent', 'author', 'organisation', 'procurementNote', 'stockDelivery'])
            ->orderBy('id')
            ->get()
            ->map(fn (StockDeliveryFeedback $feedback) => [
                ...StockDeliveryFeedbackResource::make($feedback)->resolve(),
                'open_discrepancies'     => StockDeliveryFeedbackResource::openDiscrepancies($feedback),
                'make_instruction_route' => $canEdit && $feedback->supplier_id && !$feedback->procurement_note_id
                    ? ['name' => 'grp.models.stock-delivery-feedback.instruction.store', 'parameters' => ['stockDeliveryFeedback' => $feedback->id], 'method' => 'post']
                    : null,
                'update_route'           => $feedback->author_id === $user->id
                    ? ['name' => 'grp.models.stock-delivery-feedback.update', 'parameters' => ['stockDeliveryFeedback' => $feedback->id], 'method' => 'patch']
                    : null,
            ])->all();
    }
}
