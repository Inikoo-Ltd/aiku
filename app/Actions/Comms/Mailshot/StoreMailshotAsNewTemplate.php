<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 22 Jan 2026 16:46:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateEmailTemplates;
use App\Actions\OrgAction;
use App\Enums\Comms\EmailTemplate\EmailTemplateBuilderEnum;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use App\Models\Comms\Mailshot;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\JsonResponse;

class StoreMailshotAsNewTemplate extends OrgAction
{
    public function handle(EmailTemplate|Mailshot $parent, array $modelData): EmailTemplate
    {

        if ($parent instanceof Mailshot) {
            // get default template
            $defaultMailshotTemplate = $this->group->emailTemplates()->where('builder', EmailTemplateBuilderEnum::BEEFREE->value)->where('slug', 'mailshot')->first();
            data_set($modelData, 'data', $defaultMailshotTemplate->data);
            data_set($modelData, 'language_id', $parent->shop->language_id);
            data_set($modelData, 'arguments', $defaultMailshotTemplate->arguments);
        } else {
            data_set($modelData, 'data', $parent->data);
            data_set($modelData, 'language_id', $parent->language_id);
            data_set($modelData, 'arguments', $parent->arguments);
        }

        data_set($modelData, 'organisation_id', $this->organisation->id);
        data_set($modelData, 'shop_id', $this->shop->id);
        data_set($modelData, 'builder', EmailTemplateBuilderEnum::BEEFREE->value);
        data_set($modelData, 'state', EmailTemplateStateEnum::ACTIVE->value);
        data_set($modelData, 'active_at', now());
        data_set($modelData, 'is_seeded', false);

        // TODO: update this block
        /** @var EmailTemplate $emailTemplate */
        $emailTemplate = $this->group->emailTemplates()->create($modelData);

        ShopHydrateEmailTemplates::dispatch($this->shop);

        return $emailTemplate;
    }

    public function rules(): array
    {
        $rules = [
            'name'        => ['required', 'string', 'max:255'],
            'layout'      => ['required', 'array'],
        ];

        return $rules;
    }

    public function inMailshot(Shop $shop, Mailshot $mailshot, ActionRequest $request): EmailTemplate
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot, $this->validatedData);
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
