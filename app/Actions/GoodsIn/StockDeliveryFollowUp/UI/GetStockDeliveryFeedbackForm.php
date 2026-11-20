<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryFollowUp\UI;

use App\Actions\GoodsIn\StockDeliveryFollowUp\StoreStockDeliveryFeedback;
use App\Enums\GoodsIn\StockDeliveryFollowUp\StockDeliveryFeedbackTopicEnum;
use App\Enums\GoodsIn\StockDeliveryFollowUp\StockDeliveryItemFeedbackIssueEnum;
use App\Http\Resources\GoodsIn\StockDeliveryFeedbackResource;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryFeedback;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What the delivery page needs to give and show feedback: the round's general part goes to whoever sent the delivery
 * (the agent of a consolidated container, else its supplier or partner) and each line of the items table can take a
 * note for its own supplier. No money, so the warehouse page gets it too.
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
        $round = $stockDelivery->feedback;

        return [
            'sent'                => (bool) $round?->isSent(),
            'recipient'           => StockDeliveryFeedbackResource::recipient($round ?? new StockDeliveryFeedback([
                'stock_delivery_id' => $stockDelivery->id,
                'supplier_id'       => StoreStockDeliveryFeedback::addressedSupplierId($stockDelivery),
                'agent_id'          => $stockDelivery->agent_id,
            ])),
            'purchase_orders'     => StockDeliveryFeedbackResource::purchaseOrders($round ?? new StockDeliveryFeedback(['stock_delivery_id' => $stockDelivery->id])),
            'items_noted'         => $round ? $round->items()->count() : 0,
            'rating_descriptions' => collect(self::ratingDescriptions())->map(fn (string $label, int $rating) => ['value' => $rating, 'label' => $label])->values()->all(),
            'topics'              => collect(StockDeliveryFeedbackTopicEnum::labels())->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'issues'              => collect(StockDeliveryItemFeedbackIssueEnum::labels())->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
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
            'item_store_route'    => [
                'name'       => 'grp.models.stock-delivery-item.feedback.store',
                'parameters' => [],
                'method'     => 'post',
            ],
        ];
    }

    /**
     * The round as one card, with the open discrepancies of the delivery (of its supplier when it has one) and the
     * actions the user may take on it and on each line note. The agent sees the round only once sent, without names.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function cards(StockDelivery $stockDelivery, User $user, bool $canEdit, bool $forAgent = false): array
    {
        return $stockDelivery->feedbacks()
            ->when($forAgent, fn ($query) => $query->whereNotNull('sent_at'), fn ($query) => $query->where(fn ($query) => $query->whereNotNull('sent_at')->orWhereHas('items')))
            ->with(['supplier', 'agent', 'author', 'organisation', 'procurementNote', 'stockDelivery', 'items' => fn ($query) => $query->with(['supplier', 'author', 'procurementNote', 'stockDeliveryItem.orgStock', 'stockDeliveryItem.supplierProduct'])])
            ->orderBy('id')
            ->get()
            ->map(function (StockDeliveryFeedback $feedback) use ($user, $canEdit, $forAgent) {
                $card = StockDeliveryFeedbackResource::make($feedback)->resolve();
                $card['items'] = collect($card['items'])->map(fn (array $note) => [
                    ...$note,
                    'author'                 => $forAgent ? null : $note['author'],
                    'author_id'              => $forAgent ? null : $note['author_id'],
                    'make_instruction_route' => !$forAgent && $canEdit && $note['supplier'] && !$note['instruction']
                        ? ['name' => 'grp.models.stock-delivery-item-feedback.instruction.store', 'parameters' => ['stockDeliveryItemFeedback' => $note['id']], 'method' => 'post']
                        : null,
                    'update_route'           => !$forAgent && $note['author_id'] === $user->id
                        ? ['name' => 'grp.models.stock-delivery-item-feedback.update', 'parameters' => ['stockDeliveryItemFeedback' => $note['id']], 'method' => 'patch']
                        : null,
                    'delete_route'           => !$forAgent && !$feedback->isSent() && $note['author_id'] === $user->id && !$note['instruction']
                        ? ['name' => 'grp.models.stock-delivery-item-feedback.delete', 'parameters' => ['stockDeliveryItemFeedback' => $note['id']], 'method' => 'delete']
                        : null,
                ])->all();

                return [
                    ...$card,
                    'author'                 => $forAgent ? null : $card['author'],
                    'author_id'              => $forAgent ? null : $card['author_id'],
                    'open_discrepancies'     => $forAgent ? [] : StockDeliveryFeedbackResource::openDiscrepancies($feedback),
                    'make_instruction_route' => !$forAgent && $canEdit && $feedback->isSent() && $feedback->supplier_id && !$feedback->procurement_note_id
                        ? ['name' => 'grp.models.stock-delivery-feedback.instruction.store', 'parameters' => ['stockDeliveryFeedback' => $feedback->id], 'method' => 'post']
                        : null,
                    'update_route'           => !$forAgent && $feedback->isSent() && $feedback->author_id === $user->id
                        ? ['name' => 'grp.models.stock-delivery-feedback.update', 'parameters' => ['stockDeliveryFeedback' => $feedback->id], 'method' => 'patch']
                        : null,
                ];
            })->all();
    }
}
