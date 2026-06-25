<?php

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithSendSubscribersOutboxEmail;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Outbox;
use App\Models\Reviews\Review;

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

        $this->sendOutboxEmailToSubscribers(
            $outbox,
            additionalData: [
                'customer_name'   => $customer?->name ?? '',
                'customer_link'   => $customer ? route('grp.org.shops.show.crm.customers.show', [
                    $review->organisation->slug,
                    $review->shop->slug,
                    $customer?->slug
                ]) : '#',
                'review_link'      =>  'https://app.aiku.test/org/sk',// update Later
                'review_title'     => $review->title ?? '',
                'review_message'   => $review->message ?? '',
            ]
        );
    }
}
