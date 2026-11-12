<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Retina\CRM\WhatsappOptIn;

use App\Enums\Comms\Mailshot\MailshotReadyMadeLinkEnum;
use App\Models\CRM\Customer;
use App\Models\Web\Website;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRetinaWhatsappOptInData
{
    use AsObject;

    /**
     * On the dashboard ($dismissible) the card offers Not interested and is not shown again once
     * dismissed; in the settings it is always shown, so a customer can still opt in there.
     */
    public function handle(Customer $customer, Website $website, bool $dismissible = false): ?array
    {
        $comms = $customer->comms;
        if (!$comms) {
            return null;
        }

        if ($dismissible && $comms->whatsapp_newsletter_opt_in_dismissed_at && !$comms->is_subscribed_to_whatsapp_newsletter) {
            return null;
        }

        $knownWhatsappPhone = $this->getKnownWhatsappPhone($customer);

        return [
            'anchor_id'            => MailshotReadyMadeLinkEnum::WHATSAPP_OPT_IN->anchor(),
            'is_subscribed'        => (bool)$comms->is_subscribed_to_whatsapp_newsletter,
            'whatsapp_phone'       => $knownWhatsappPhone,
            'suggested_phone'      => $knownWhatsappPhone ? null : $this->getSuggestedPhone($customer->phone),
            'default_country'      => $customer->address?->country_code,
            'consent_text'         => SubscribeRetinaCustomerToWhatsappNewsletter::consentText($customer),
            'privacy_policy_url'   => $website->getPrivacyPolicyUrl(),
            'subscribe_route'      => [
                'name'       => 'retina.models.whatsapp_newsletter.subscribe',
                'parameters' => [],
                'method'     => 'post',
            ],
            'dismiss_route'        => $dismissible ? [
                'name'       => 'retina.models.whatsapp_newsletter.dismiss',
                'parameters' => [],
                'method'     => 'post',
            ] : null,
            'unsubscribe_route'    => [
                'name'       => 'retina.models.customer_comms.update',
                'parameters' => ['customerComms' => $comms->id],
                'method'     => 'patch',
            ],
        ];
    }

    public function getKnownWhatsappPhone(Customer $customer): ?string
    {
        if ($customer->marketing_whatsapp_phone) {
            return $customer->marketing_whatsapp_phone;
        }

        $chatPhone = DB::table('meta_chat_sessions')
            ->where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->whereNotNull('phone_number')
            ->orderByDesc('last_visitor_message_at')
            ->value('phone_number');

        return SubscribeRetinaCustomerToWhatsappNewsletter::normalisePhone($chatPhone);
    }

    public function getSuggestedPhone(?string $phone): ?string
    {
        $raw = trim((string)$phone);
        if (!str_starts_with($raw, '+') && !str_starts_with($raw, '00')) {
            return null;
        }

        return SubscribeRetinaCustomerToWhatsappNewsletter::normalisePhone($raw);
    }

}
