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
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class SubscribeRetinaCustomerToWhatsappNewsletter extends RetinaAction
{
    public const SOURCE = 'retina_dashboard';

    private const E164 = '/^\+[1-9][0-9]{7,14}$/';

    /**
     * The newsletter consent, ticked with the `consent` box: offers and news, which Meta bills
     * as marketing.
     */
    public static function consentText(Customer $customer): string
    {
        return __('Yes, I would like to receive occasional offers and news from :brand on WhatsApp.', [
            'brand' => $customer->shop->name,
        ]);
    }

    /**
     * The order updates consent, ticked with the `notifications` box: the utility messages the
     * WhatsApp outboxes send, kept apart from the newsletter so either can be chosen alone.
     */
    public static function notificationsConsentText(Customer $customer): string
    {
        return __('Yes, I would like :brand to send me WhatsApp updates about my orders, deliveries, restocks and membership.', [
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

    /**
     * Each box sets its own subscription when it is sent, so unticking one while editing turns
     * only that one off. A box that is not sent at all leaves its subscription as it is.
     */
    public function handle(Customer $customer, array $modelData): ?WhatsappSubscriber
    {
        $comms = $customer->comms;
        if (!$comms) {
            throw ValidationException::withMessages([
                'phone' => __('We could not save your WhatsApp preference, please try again later.'),
            ]);
        }

        return DB::transaction(function () use ($customer, $comms, $modelData) {
            $commsData = [];

            if (Arr::has($modelData, 'consent')) {
                $commsData['is_subscribed_to_whatsapp_newsletter'] = (bool)$modelData['consent'];
            }

            if (Arr::has($modelData, 'notifications')) {
                $commsData['is_subscribed_to_whatsapp_notifications'] = (bool)$modelData['notifications'];

                if ($modelData['notifications'] && !$comms->is_subscribed_to_whatsapp_notifications) {
                    $commsData['whatsapp_notifications_subscribed_origin'] = static::SOURCE;
                }
            }

            if ($commsData) {
                UpdateCustomerComms::run($comms, $commsData);
            }
            $comms->update(['whatsapp_newsletter_opt_in_dismissed_at' => null]);

            $customer->update(['marketing_whatsapp_phone' => $modelData['phone']]);

            if (!$comms->is_subscribed_to_whatsapp_newsletter) {
                return null;
            }

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
            'phone'         => ['required', 'string', 'max:20', 'regex:'.self::E164],
            'consent'       => ['sometimes', 'boolean'],
            'notifications' => ['sometimes', 'boolean'],
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
        ];
    }

    /**
     * At least one box must be ticked: unsubscribing from both is the Unsubscribe link's job.
     */
    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $isTicked = fn (string $field) => filter_var($this->get($field), FILTER_VALIDATE_BOOLEAN);

        if (!$isTicked('consent') && !$isTicked('notifications')) {
            $validator->errors()->add('consent', __('Please tick at least one box to choose which WhatsApp messages you would like.'));
        }
    }

    public function asController(ActionRequest $request): ?WhatsappSubscriber
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
