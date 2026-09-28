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

        return $this->update($organisation, ['settings' => $settings]);
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

        $this->handle($organisation, $this->validatedData);

        return back();
    }
}
