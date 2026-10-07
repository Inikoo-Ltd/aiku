<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\Settings;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
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
        ];
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
