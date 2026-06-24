<?php

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithSendSubscribersOutboxEmail;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;

class SendNewReviewEmailToSubscribers extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendSubscribersOutboxEmail;

    public function handle(Shop $shop, array $reviewData = []): void
    {
        /** @var Outbox $outbox */
        $outbox = $shop->outboxes()->where('code', OutboxCodeEnum::NEW_REVIEW->value)->first();

        if (!$outbox) {
            return;
        }

        $this->sendOutboxEmailToSubscribers(
            $outbox,
            additionalData: [
                'customer_name'   => $reviewData['customer_name'] ?? '',
                'customer_link'   => $reviewData['customer_link'] ?? '',
                'product_name'    => $reviewData['product_name'] ?? '',
                'product_link'    => $reviewData['product_link'] ?? '',
                'rating'          => $reviewData['rating'] ?? '',
                'review_comment'  => $reviewData['review_comment'] ?? '',
            ]
        );
    }
}
