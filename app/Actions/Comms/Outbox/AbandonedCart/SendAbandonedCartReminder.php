<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 09 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Outbox\AbandonedCart;

use App\Actions\Comms\Traits\WithOrderingCustomerNotification;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Ordering\CheckoutAbandonment\CheckoutAbandonmentStateEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Ordering\CheckoutAbandonment;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class SendAbandonedCartReminder extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithOrderingCustomerNotification;
    use WithSendBulkEmails;
    use WithAbandonedCartRecoveryContent;

    private CheckoutAbandonment $checkoutAbandonment;

    public function handle(CheckoutAbandonment $checkoutAbandonment): ?DispatchedEmail
    {
        if ($checkoutAbandonment->email_sent_at) {
            return null;
        }

        if ($checkoutAbandonment->state !== CheckoutAbandonmentStateEnum::ABANDONED) {
            return null;
        }

        $customer = $checkoutAbandonment->customer;
        if (!$customer || !$customer->email) {
            return null;
        }

        $previousLocale = app()->getLocale();
        app()->setLocale($customer->shop->language->code);

        [$emailHtmlBody, $dispatchedEmail] = $this->getEmailBody($customer, OutboxCodeEnum::ABANDONED_CART);
        if (!$emailHtmlBody || !$dispatchedEmail) {
            app()->setLocale($previousLocale);

            return null;
        }

        $outbox      = $dispatchedEmail->outbox;
        $checkoutUrl = $this->getCheckoutUrl($outbox);

        $this->sendEmailWithMergeTags(
            $dispatchedEmail,
            $outbox->emailOngoingRun->sender(),
            $outbox->emailOngoingRun?->email?->subject ?? '',
            $emailHtmlBody,
            '',
            additionalData: [
                'checkout_url'         => $checkoutUrl,
                'abandoned_cart_items' => $this->generateRecoveryContent($checkoutAbandonment->order_id, $checkoutUrl),
            ],
            senderName: $outbox->emailOngoingRun->senderName(),
        );

        $checkoutAbandonment->update(['email_sent_at' => now()]);

        app()->setLocale($previousLocale);

        return $dispatchedEmail;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("orders.{$this->shop->id}.view");
    }

    public function asController(CheckoutAbandonment $checkoutAbandonment, ActionRequest $request): ?DispatchedEmail
    {
        $this->checkoutAbandonment = $checkoutAbandonment;
        $this->initialisationFromShop($checkoutAbandonment->shop, $request);

        return $this->handle($checkoutAbandonment);
    }

    public function htmlResponse(?DispatchedEmail $dispatchedEmail): RedirectResponse
    {
        return back();
    }
}
