<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryFollowUp;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Enums\UI\Procurement\StockDeliveryTabsEnum;
use App\Models\GoodsIn\StockDeliveryFeedback;
use App\Models\GoodsIn\StockDeliveryItemFeedback;
use App\Models\SysAdmin\User;
use App\Notifications\StockDeliveryFeedbackNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Tells the buyers what a feedback round says, one notification per buyer: the buyer of the general part gets the
 * stars, the buyer of each flagged line its notes. Notes are marked notified so a note is told once.
 */
class NotifyStockDeliveryFeedback
{
    use AsObject;

    /**
     * @param Collection<int, StockDeliveryItemFeedback> $itemNotes
     */
    public function handle(StockDeliveryFeedback $round, User $author, Collection $itemNotes, bool $withGeneral): void
    {
        $claimed   = self::claim($itemNotes->pluck('id')->all());
        $itemNotes = $itemNotes->filter(fn (StockDeliveryItemFeedback $note) => in_array($note->id, $claimed, true))->values();
        if (!$withGeneral && $itemNotes->isEmpty()) {
            return;
        }

        $byBuyer = $itemNotes->groupBy(fn (StockDeliveryItemFeedback $note) => (int) $note->buyer_id);
        $buyers  = $byBuyer->keys()->filter();
        if ($withGeneral && $round->buyer_id) {
            $buyers->push($round->buyer_id);
        }

        foreach ($buyers->unique() as $buyerId) {
            $buyer = User::find($buyerId);
            if (!$buyer || !$buyer->status || $buyer->id === $author->id) {
                continue;
            }

            $notes = $byBuyer->get($buyerId, collect());
            $this->notify($buyer, $round, $withGeneral && $round->buyer_id === $buyerId, $notes);
        }
    }

    /**
     * Marks the notes not told yet as told and returns them: a note sent with the round and stored at the same moment
     * is told by whichever gets it first.
     *
     * @param array<int, int> $noteIds
     * @return array<int, int>
     */
    private static function claim(array $noteIds): array
    {
        if (!$noteIds) {
            return [];
        }

        return array_map(fn ($row) => (int) $row->id, DB::select(
            'UPDATE stock_delivery_item_feedbacks SET notified_at = now() WHERE notified_at IS NULL AND id IN ('.implode(',', array_fill(0, count($noteIds), '?')).') RETURNING id',
            $noteIds
        ));
    }

    /**
     * @param Collection<int, StockDeliveryItemFeedback> $notes
     */
    private function notify(User $buyer, StockDeliveryFeedback $round, bool $withGeneral, Collection $notes): void
    {
        $stockDelivery = $round->stockDelivery;

        $title = $withGeneral
            ? __(':stars stars on delivery :reference', ['stars' => $round->rating, 'reference' => $stockDelivery->reference])
            : __('Items reported on delivery :reference', ['reference' => $stockDelivery->reference]);
        if ($withGeneral && $notes->isNotEmpty()) {
            $title .= ' · '.trans_choice(':count item reported|:count items reported', $notes->count(), ['count' => $notes->count()]);
        }

        $lines = $notes->map(fn (StockDeliveryItemFeedback $note) => trim(($note->stockDeliveryItem?->orgStock?->code ?? '').': '.$note->issue->label().($note->comment ? ' · '.$note->comment : '')));
        $body  = collect([$withGeneral ? $round->comment : null])->merge($lines)->filter()->implode("\n");

        $route = [
            'name'       => 'grp.org.procurement.stock_deliveries.show',
            'parameters' => ['organisation' => $stockDelivery->organisation->slug, 'stockDelivery' => $stockDelivery->slug, 'tab' => StockDeliveryTabsEnum::FEEDBACK->value],
        ];

        NotifyUserOnChosenChannels::run(
            $buyer,
            UserNotificationEnum::DELIVERY_FEEDBACK,
            new StockDeliveryFeedbackNotification($title, $body, UserNotificationEnum::DELIVERY_FEEDBACK->value, (string) $round->id, $route),
            $title,
            $body,
            route($route['name'], $route['parameters'])
        );
    }
}
