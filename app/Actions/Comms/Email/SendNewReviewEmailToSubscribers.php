<?php

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithSendSubscribersOutboxEmail;
use App\Actions\Helpers\Images\GetPictureSources;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Outbox;
use App\Models\Helpers\Media;
use App\Models\Reviews\Review;
use Illuminate\Support\Arr;

class SendNewReviewEmailToSubscribers extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendSubscribersOutboxEmail;

    public function handle(int $reviewId): void
    {

        $review = Review::find($reviewId);
        if (!$review) {
            return;
        }

        if ($review->shop->type === ShopTypeEnum::EXTERNAL) {
            return;
        }

        /** @var Outbox $outbox */
        $outbox = $review->shop->outboxes()->where('code', OutboxCodeEnum::NEW_REVIEW->value)->first();

        if (!$outbox) {
            return;
        }

        $customer = $review->customer ?? null;
        $order    = $review->order ?? null;

        $this->sendOutboxEmailToSubscribers(
            $outbox,
            additionalData: [
                'customer_name'      => $customer?->name ?? '',
                'customer_link'      => $customer ? route('grp.org.shops.show.crm.customers.show', [
                    $review->organisation->slug,
                    $review->shop->slug,
                    $customer?->slug
                ]) : '#',
                'review_link'        => $order ? route('grp.org.shops.show.reviews.backlog', [
                    $review->organisation->slug,
                    $review->shop->slug,
                    'waiting_filter' => ['ID' => $review->id],
                ]) : '#',
                'review_message'     => $review->message ?? '',
                'order_reference'    => $order?->reference ?? '',
                'order_link' => $order ? route('grp.org.shops.show.ordering.orders.show', [
                    $review->organisation->slug,
                    $review->shop->slug,
                    $order->slug
                ]) : '#',
                'rating_main'        => $review->rating_main,
                'blade_review_images' => $this->generateReviewImagesHtml($review),
            ]
        );
    }

    private function generateReviewImagesHtml(Review $review): string
    {
        $urls = [];

        foreach ($review->images as $media) {
            /** @var Media $media */
            $urls[] = Arr::get(GetPictureSources::run($media->getImage()->resize(200, 200)), 'png', '');
        }

        foreach (Arr::get($review->web_images ?? [], 'main', []) as $webImageUrl) {
            $urls[] = $webImageUrl;
        }

        $urls = array_filter($urls);

        if (empty($urls)) {
            return '';
        }

        $html = '';
        foreach ($urls as $url) {
            $html .= sprintf(
                '<img src="%s" width="100" height="100" style="display:inline-block;margin:4px;border-radius:6px;object-fit:cover;" />',
                $url
            );
        }

        return $html;
    }
}
