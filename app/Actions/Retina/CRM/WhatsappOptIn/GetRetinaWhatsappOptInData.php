<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Retina\CRM\WhatsappOptIn;

use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRetinaWhatsappOptInData
{
    use AsObject;

    public function handle(Customer $customer, Website $website): ?array
    {
        $comms = $customer->comms;
        if (!$comms) {
            return null;
        }

        $knownWhatsappPhone = $this->getKnownWhatsappPhone($customer);

        return [
            'is_subscribed'        => (bool)$comms->is_subscribed_to_whatsapp_newsletter,
            'whatsapp_phone'       => $knownWhatsappPhone,
            'suggested_phone'      => $knownWhatsappPhone ? null : $this->getSuggestedPhone($customer->phone),
            'default_country'      => $customer->address?->country_code,
            'consent_text'         => SubscribeRetinaCustomerToWhatsappNewsletter::consentText($customer),
            'privacy_policy_url'   => $this->getPrivacyPolicyUrl($website),
            'subscribe_route'      => [
                'name'       => 'retina.models.whatsapp_newsletter.subscribe',
                'parameters' => [],
                'method'     => 'post',
            ],
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

    protected function getPrivacyPolicyUrl(Website $website): ?string
    {
        $canonicalUrl = Webpage::where('website_id', $website->id)
            ->where('sub_type', WebpageSubTypeEnum::PRIVACY)
            ->where('state', WebpageStateEnum::LIVE)
            ->value('canonical_url');

        return $canonicalUrl ? ShowIrisWebpage::make()->getEnvironmentUrl($canonicalUrl) : null;
    }
}
