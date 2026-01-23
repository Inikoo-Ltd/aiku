<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 22 Jan 2026 16:46:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Enums\Comms\EmailTemplate\EmailTemplateBuilderEnum;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\JsonResponse;

class StoreMailshotAsNewTemplate extends OrgAction
{
    public function handle(EmailTemplate $emailTemplate, array $modelData): EmailTemplate
    {
        data_set($modelData, 'organisation_id', $this->organisation->id);
        data_set($modelData, 'shop_id', $this->shop->id);
        data_set($modelData, 'builder', EmailTemplateBuilderEnum::BEEFREE->value);
        data_set($modelData, 'data', $emailTemplate->data);
        data_set($modelData, 'language_id', $emailTemplate->language_id);
        data_set($modelData, 'state', EmailTemplateStateEnum::ACTIVE->value);
        data_set($modelData, 'active_at', now());
        data_set($modelData, 'is_seeded', false);
        data_set($modelData, 'arguments', $emailTemplate->arguments);

        // TODO: update this block
        /** @var EmailTemplate $emailTemplate */
        $emailTemplate = $this->group->emailTemplates()->create($modelData);


        return $emailTemplate;
    }

    public function rules(): array
    {
        $rules = [
            'name'        => ['required', 'string', 'max:255'],
            'layout'      => ['required', 'array'],
            'compiled_layout' => ['required', 'string'],
        ];

        return $rules;
    }

    public function asController(Shop $shop, EmailTemplate $emailTemplate, ActionRequest $request): EmailTemplate
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($emailTemplate, $this->validatedData);
    }

    public function jsonResponse(EmailTemplate $emailTemplate): JsonResponse
    {
        return response()->json($emailTemplate);
    }
}
