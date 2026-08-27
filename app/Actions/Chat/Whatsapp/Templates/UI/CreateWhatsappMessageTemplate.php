<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates\UI;

use App\Actions\Chat\Whatsapp\Templates\GetWhatsappTemplateLanguages;
use App\Actions\Chat\Whatsapp\Templates\GetWhatsappTemplateTags;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateWhatsappMessageTemplate extends OrgAction
{
    public function handle(Shop $shop, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Chat/CreateWhatsappTemplate',
            [
                'breadcrumbs' => IndexWhatsappMessageTemplates::make()->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('New WhatsApp template'),
                'pageHead'    => [
                    'title' => __('New WhatsApp template'),
                    'icon'  => [
                        'icon'  => ['fab', 'fa-whatsapp'],
                        'title' => __('New WhatsApp template'),
                    ],
                ],
                'isConfigured'  => filled(Arr::get($shop->settings, 'whatsapp.waba_id')),
                'canUploadMedia' => filled(config('meta.whatsapp.app_id')),
                'languages'     => GetWhatsappTemplateLanguages::run(),
                'mergeTags'     => GetWhatsappTemplateTags::run($shop),
                'businessName'  => $shop->name,
                'submitRoute'   => [
                    'name'       => 'grp.org.shops.show.chat.whatsapp_templates.store',
                    'parameters' => [
                        'organisation' => $shop->organisation->slug,
                        'shop'         => $shop->slug,
                    ],
                ],
            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request);
    }
}
