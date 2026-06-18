<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 18 june 2026 10:43:58 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithSendSubscribersOutboxEmail;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Outbox;
use App\Models\Discounts\Offer;

class SendFinishOfferEmailToSubscribers extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendSubscribersOutboxEmail;

    public function handle(int $offerId): void
    {
        $offer = Offer::find($offerId);
        if (!$offer) {
            return;
        }

        if ($offer->shop->type === ShopTypeEnum::EXTERNAL) {
            return;
        }

        /** @var Outbox $outbox */
        $outbox = $offer->shop->outboxes()->where('code', OutboxCodeEnum::FINISH_OFFER->value)->first();

        $this->sendOutboxEmailToSubscribers(
            $outbox,
            additionalData: [
                'shop_name'              => $offer->shop->name,
                'offer_code'             => $offer->code ?? 'N/A',
                'offer_link'             => route('grp.org.shops.show.discounts.offers.show', [
                    $offer->shop->organisation->slug,
                    $offer->shop->slug,
                    $offer->slug
                ]),
                'offer_name'             => $offer->name,
                'campaign_link'          => route('grp.org.shops.show.discounts.campaigns.show', [
                    $offer->shop->organisation->slug,
                    $offer->shop->slug,
                    $offer->offerCampaign->slug
                ]),
                'campaign_name'          => $offer->offerCampaign->name ?? 'N/A',
                'created_date'           => $offer->created_at->format('F jS, Y'),
                'offer_state'            => $offer->state->value,
                'offer_type'             => $offer->type,
                'duration'               => $offer->duration->value,
                'start_date'             => $offer->start_at?->format('F jS, Y') ?? 'N/A',
                'end_date'               => $offer->end_at?->format('F jS, Y') ?? 'N/A',
                'discount_type'          => $offer->offerAllowances->first()?->type->label() ?? 'N/A',
                'blade_discount_details' => '',
                'trigger_type'           => $offer->trigger_type ?? 'N/A',
                'voucher'                => $offer->voucher ?? 'N/A',
                'customer_id'            => $offer->customer_id ?? 'N/A'
            ]
        );
    }
}
