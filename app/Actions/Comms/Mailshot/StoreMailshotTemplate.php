<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 21 Jan 2026 16:46:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Enums\Comms\EmailTemplate\EmailTemplateBuilderEnum;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;

class StoreMailshotTemplate extends OrgAction
{
    public function handle(array $modelData): EmailTemplate
    {
        //  Use default mailshot template
        $defaultMailshotTemplate = $this->group->emailTemplates()->where('builder', EmailTemplateBuilderEnum::BEEFREE->value)->where('slug', 'mailshot')->first();

        if (!$defaultMailshotTemplate) {
            throw new \Exception('Default mailshot template not found');
        }

        data_set($modelData, 'organisation_id', $this->organisation->id);
        data_set($modelData, 'shop_id', $this->shop->id);
        data_set($modelData, 'builder', EmailTemplateBuilderEnum::BEEFREE->value);
        data_set($modelData, 'data', $defaultMailshotTemplate->data);
        data_set($modelData, 'language_id', $defaultMailshotTemplate->language_id);
        data_set($modelData, 'state', EmailTemplateStateEnum::ACTIVE->value);
        data_set($modelData, 'active_at', now());
        data_set($modelData, 'is_seeded', false);
        data_set($modelData, 'arguments', $defaultMailshotTemplate->arguments);
        data_set($modelData, 'layout', $defaultMailshotTemplate->layout);

        // TODO: update this block
        /** @var EmailTemplate $emailTemplate */
        $emailTemplate = $this->group->emailTemplates()->create($modelData);


        return $emailTemplate;
    }

    public function rules(): array
    {
        $rules = [
            'name'        => ['required', 'string', 'max:255'],
        ];

        return $rules;
    }

    public function asController(Shop $shop, ActionRequest $request): EmailTemplate
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($this->validatedData);
    }

    public function htmlResponse(EmailTemplate $emailTemplate): \Symfony\Component\HttpFoundation\Response
    {

        return Inertia::location(route('grp.org.shops.show.marketing.templates.workshop', [
            'organisation'      => $this->organisation->slug,
            'shop'              => $this->shop->slug,
            'emailTemplate'     => $emailTemplate->slug
        ]));
    }
}
