<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\Settings;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Lorisleiva\Actions\ActionRequest;

class UpdateProcurementSettings extends OrgAction
{
    use WithActionUpdate;

    private const array WHATSAPP_FIELDS = [
        'whatsapp_phone_number_id'         => 'phone_number_id',
        'whatsapp_waba_id'                 => 'waba_id',
        'whatsapp_display_phone'           => 'display_phone',
        'whatsapp_message_template'        => 'message_template',
        'whatsapp_purchase_order_template' => 'purchase_order_template',
        'whatsapp_template_language'       => 'template_language',
    ];

    private const array WHATSAPP_TEMPLATE_FIELDS = [
        'whatsapp_message_template'        => 'message_template',
        'whatsapp_purchase_order_template' => 'purchase_order_template',
    ];

    private const array WECHAT_FIELDS = [
        'wechat_corp_id'     => 'corp_id',
        'wechat_key_version' => 'key_version',
    ];

    private const array WECHAT_SECRET_FIELDS = [
        'wechat_archive_secret' => 'archive_secret',
        'wechat_contact_secret' => 'contact_secret',
        'wechat_private_key'    => 'private_key',
        'wechat_callback_token'   => 'callback_token',
        'wechat_encoding_aes_key' => 'encoding_aes_key',
    ];

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['org-admin.'.$this->organisation->id, 'org-supervisor.'.$this->organisation->id.'.procurement']);
    }

    public function handle(Organisation $organisation, array $modelData): Organisation
    {
        $settings = $organisation->settings ?? [];

        foreach (self::WHATSAPP_FIELDS as $field => $key) {
            if (array_key_exists($field, $modelData)) {
                data_set($settings, "procurement.whatsapp.$key", filled($modelData[$field]) ? trim((string) $modelData[$field]) : null);
            }
        }

        foreach (self::usesWechat($organisation) ? self::WECHAT_FIELDS : [] as $field => $key) {
            if (array_key_exists($field, $modelData)) {
                data_set($settings, "procurement.wechat.$key", filled($modelData[$field]) ? trim((string) $modelData[$field]) : null);
            }
        }

        foreach (self::usesWechat($organisation) ? self::WECHAT_SECRET_FIELDS : [] as $field => $key) {
            if (filled(Arr::get($modelData, $field))) {
                data_set($settings, "procurement.wechat.$key", Crypt::encryptString(trim((string) $modelData[$field])));
            }
        }

        $organisation->settings = $settings;

        foreach (self::WHATSAPP_TEMPLATE_FIELDS as $field => $key) {
            if (! array_key_exists($field, $modelData)) {
                continue;
            }

            $name  = data_get($settings, "procurement.whatsapp.$key");
            $fetch = $name ? FetchProcurementWhatsappTemplate::run($organisation, $name) : null;

            data_set($settings, "procurement.whatsapp.{$key}_meta", $fetch);

            if (blank(data_get($settings, 'procurement.whatsapp.template_language')) && filled(Arr::get($fetch, 'template.language'))) {
                data_set($settings, 'procurement.whatsapp.template_language', $fetch['template']['language']);
            }
        }

        return $this->update($organisation, ['settings' => $settings]);
    }

    /**
     * Suppliers reach procurement on WeChat only through the agents in China, so no other
     * organisation is offered the connection.
     */
    public static function usesWechat(Organisation $organisation): bool
    {
        return $organisation->type === OrganisationTypeEnum::AGENT && $organisation->country?->code === 'CN';
    }

    /**
     * @param  array{fetch_status: string, error: string|null, template: array<string, mixed>|null}  $fetch
     * @return array{status: string, title: string, description: string}
     */
    public static function fetchNotification(string $name, array $fetch): array
    {
        if ($fetch['fetch_status'] !== 'found') {
            return ['status' => 'error', 'title' => __('Template :name not fetched from Meta', ['name' => $name]), 'description' => (string) $fetch['error']];
        }

        $status      = (string) Arr::get($fetch, 'template.status');
        $description = __('Status :status, language :language.', ['status' => $status, 'language' => Arr::get($fetch, 'template.language')]);

        if ($status !== 'APPROVED') {
            return ['status' => 'warning', 'title' => __('Template :name is not approved yet', ['name' => $name]), 'description' => $description];
        }

        return ['status' => 'success', 'title' => __('Template :name fetched from Meta', ['name' => $name]), 'description' => $description];
    }

    public function rules(): array
    {
        return [
            'whatsapp_phone_number_id'         => ['sometimes', 'nullable', 'string', 'regex:/^\d{6,32}$/'],
            'whatsapp_waba_id'                 => ['sometimes', 'nullable', 'string', 'regex:/^\d{6,32}$/'],
            'whatsapp_display_phone'           => ['sometimes', 'nullable', 'string', 'max:32'],
            'whatsapp_message_template'        => ['sometimes', 'nullable', 'string', 'max:512'],
            'whatsapp_purchase_order_template' => ['sometimes', 'nullable', 'string', 'max:512'],
            'whatsapp_template_language'       => ['sometimes', 'nullable', 'string', 'max:16'],
            'wechat_corp_id'                   => ['sometimes', 'nullable', 'string', 'regex:/^ww[0-9a-zA-Z]{8,32}$/'],
            'wechat_archive_secret'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'wechat_contact_secret'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'wechat_private_key'               => ['sometimes', 'nullable', 'string', 'max:8192', $this->isRsaPrivateKey(...)],
            'wechat_key_version'               => ['sometimes', 'nullable', 'integer', 'min:1'],
            'wechat_callback_token'            => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z0-9]{1,32}$/'],
            'wechat_encoding_aes_key'          => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z0-9]{43}$/'],
        ];
    }

    private function isRsaPrivateKey(string $attribute, mixed $value, Closure $fail): void
    {
        $key = openssl_pkey_get_private(trim((string) $value));

        if ($key === false || openssl_pkey_get_details($key)['type'] !== OPENSSL_KEYTYPE_RSA) {
            $fail(__('Paste the whole RSA private key, including the BEGIN and END lines.'));
        }
    }

    public function asController(Organisation $organisation, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($organisation, $request);

        $organisation = $this->handle($organisation, $this->validatedData);

        foreach (self::WHATSAPP_TEMPLATE_FIELDS as $field => $key) {
            $fetch = Arr::get($organisation->settings, "procurement.whatsapp.{$key}_meta");

            if (array_key_exists($field, $this->validatedData) && $fetch) {
                return back()->with('notification', self::fetchNotification(Arr::get($organisation->settings, "procurement.whatsapp.$key"), $fetch));
            }
        }

        return back();
    }
}
