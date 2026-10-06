<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 02 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Comms\Mailshot\StoreMailshot;
use App\Actions\OrgAction;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use App\Models\Discounts\Offer;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class StoreCustomerListVoucherMailshot extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Offer $offer): Mailshot
    {
        /** @var Outbox|null $outbox */
        $outbox = $offer->shop->outboxes()->where('code', OutboxCodeEnum::MARKETING->value)->first();

        if (!$outbox) {
            throw ValidationException::withMessages([
                'outbox' => __('This shop has no marketing outbox, so mailshots cannot be sent from it.'),
            ]);
        }

        return StoreMailshot::make()->action($outbox, [
            'subject'           => $offer->name,
            'name'              => $offer->name,
            'type'              => MailshotTypeEnum::MARKETING->value,
            'recipients_recipe' => [
                'voucher_recipients' => [
                    'value' => $offer->id,
                ],
            ],
        ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("discounts.{$this->shop->id}.edit");
    }

    /**
     * @throws \Throwable
     */
    public function asController(Offer $offer, ActionRequest $request): Mailshot
    {
        if (!$offer->hasCustomerList()) {
            abort(404);
        }

        $this->initialisationFromShop($offer->shop, $request);

        return $this->handle($offer);
    }

    public function htmlResponse(Mailshot $mailshot): Response
    {
        return Inertia::location(route('grp.org.shops.show.marketing.mailshots.workshop', [
            'organisation' => $mailshot->shop->organisation->slug,
            'shop'         => $mailshot->shop->slug,
            'mailshot'     => $mailshot->slug,
        ]));
    }
}
