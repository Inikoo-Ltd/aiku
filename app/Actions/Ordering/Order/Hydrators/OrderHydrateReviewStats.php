<?php

namespace App\Actions\Ordering\Order\Hydrators;

use App\Actions\Reviews\Hydrators\Concerns\BuildsReviewStats;
use App\Models\Ordering\Order;
use App\Models\Reviews\Review;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrderHydrateReviewStats implements ShouldBeUnique
{
    use AsAction;
    use BuildsReviewStats;

    public function getJobUniqueId(int|null $orderId): string
    {
        return (string) ($orderId ?? 'empty');
    }

    public function handle(int|null $orderId): void
    {
        if (!$orderId) {
            return;
        }

        $order = Order::query()->find($orderId);
        if (!$order) {
            return;
        }

        $stats = $this->buildReviewStats(
            Review::query()->where('order_id', $order->id)
        );

        $order->reviewStats()->updateOrCreate([], $stats);
    }
}
