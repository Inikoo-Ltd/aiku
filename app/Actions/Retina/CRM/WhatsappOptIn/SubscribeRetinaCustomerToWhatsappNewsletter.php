<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Retina\CRM\WhatsappOptIn;

use App\Actions\Comms\WhatsappCampaign\GetWhatsappRecipientsQuery;
use App\Actions\Comms\WhatsappSubscriber\SyncCustomerWhatsappSubscriber;
use App\Actions\CRM\CustomerComms\UpdateCustomerComms;
use App\Actions\RetinaAction;
use App\Models\Comms\WhatsappSubscriber;
use App\Models\CRM\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SubscribeRetinaCustomerToWhatsappNewsletter extends RetinaAction
{
    public const SOURCE = 'retina_dashboard';

    private const E164 = '/^\+[1-9][0-9]{7,14}$/';

    public static function consentText(Customer $customer): string
    {
        return __('Yes, I would like to receive WhatsApp messages from :brand about my orders, membership, restocks and occasional offers.', [
            'brand' => $customer->shop->name,
        ]);
    }

    public static function normalisePhone(?string $phone): ?string
    {
        $phone = static::stripPhoneFormatting($phone);

        if (preg_match(self::E164, $phone) !== 1 || !GetWhatsappRecipientsQuery::isSendablePhone($phone)) {
            return null;
        }

        return $phone;
    }

    public function handle(Customer $customer, array $modelData): WhatsappSubscriber
    {
        $comms = $customer->comms;
        if (!$comms) {
            throw ValidationException::withMessages([
                'phone' => __('We could not save your WhatsApp preference, please try again later.'),
            ]);
        }

        return DB::transaction(function () use ($customer, $comms, $modelData) {
            if (!$comms->is_subscribed_to_whatsapp_newsletter) {
                UpdateCustomerComms::run($comms, ['is_subscribed_to_whatsapp_newsletter' => true]);
            }

            $customer->update(['marketing_whatsapp_phone' => $modelData['phone']]);

            $subscriber = SyncCustomerWhatsappSubscriber::run($customer);

            $subscriber->update([
                'consented_at' => now(),
                'consent'      => [
                    'phone'       => $modelData['phone'],
                    'text'        => static::consentText($customer),
                    'locale'      => app()->getLocale(),
                    'source'      => static::SOURCE,
                    'web_user_id' => ($this->webUser ?? null)?->id,
                    'ip'          => request()->ip(),
                    'utm'         => array_filter(Arr::only($modelData, ['utm_source', 'utm_medium', 'utm_campaign'])),
                ],
            ]);

            return $subscriber;
        });
    }

    public static function stripPhoneFormatting(?string $phone): string
    {
        $phone = preg_replace('/[\s\-.()\/]/', '', trim((string)$phone));

        if (str_starts_with($phone, '00')) {
            return '+'.substr($phone, 2);
        }

        return $phone;
    }

    public function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->set('phone', static::stripPhoneFormatting($this->get('phone')));
        }
    }

    public function rules(): array
    {
        return [
            'phone'        => ['required', 'string', 'max:20', 'regex:'.self::E164],
            'consent'      => ['accepted'],
            'utm_source'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'utm_medium'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'utm_campaign' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'phone.required'   => __('Please enter your WhatsApp number.'),
            'phone.regex'      => __('Please enter a valid mobile number including the country code.'),
            'consent.accepted' => __('Please tick the box to confirm you would like WhatsApp updates.'),
        ];
    }

    public function asController(ActionRequest $request): WhatsappSubscriber
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
