<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates\UI;

use App\Actions\Chat\Whatsapp\Templates\GetWhatsappTemplateLanguages;
use App\Actions\Chat\Whatsapp\Templates\GetWhatsappTemplateTags;
use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\WhatsappMediaTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateWhatsappMessageTemplate extends OrgAction
{
    use WithWhatsappCredentials;

    public function handle(Shop $shop, ActionRequest $request, ?MetaMessageTemplate $draft = null): Response
    {
        $title = $draft ? $draft->name : __('New WhatsApp template');

        return Inertia::render(
            'Org/Chat/CreateWhatsappTemplate',
            [
                'breadcrumbs' => IndexWhatsappMessageTemplates::make()->getBreadcrumbs([
                    'organisation' => $shop->organisation->slug,
                    'shop'         => $shop->slug,
                ]),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fab', 'fa-whatsapp'],
                        'title' => $title,
                    ],
                ],
                'draft'         => $draft ? [
                    'id'           => $draft->id,
                    'name'         => $draft->name,
                    'header_media' => $draft->headerMedia ? [
                        'name' => $draft->headerMedia->file_name,
                        'url'  => $draft->imageSources(0, 0, 'headerMedia'),
                    ] : null,
                ] + Arr::get($draft->data ?? [], 'draft', []) : null,
                'isConfigured'  => filled(Arr::get($shop->settings, 'whatsapp.waba_id')),
                'canUploadMedia' => filled($this->metaAppCredentials($shop->organisation)['app_id']),
                'languages'     => GetWhatsappTemplateLanguages::run(),
                'mergeTags'     => GetWhatsappTemplateTags::run($shop),
                'mediaRules'    => WhatsappMediaTypeEnum::forFrontend(),
                'businessName'  => $shop->name,
                'submitRoute'   => $draft
                    ? $this->templateRoute('draft.submit', $shop, $draft)
                    : [
                        'name'       => 'grp.org.shops.show.chat.whatsapp_templates.store',
                        'parameters' => [
                            'organisation' => $shop->organisation->slug,
                            'shop'         => $shop->slug,
                        ],
                    ],
                'draftRoute'    => $draft
                    ? $this->templateRoute('draft.update', $shop, $draft)
                    : [
                        'name'       => 'grp.org.shops.show.chat.whatsapp_templates.draft.store',
                        'parameters' => [
                            'organisation' => $shop->organisation->slug,
                            'shop'         => $shop->slug,
                        ],
                    ],
            ]
        );
    }

    /**
     * @return array{name: string, parameters: array<string, mixed>}
     */
    protected function templateRoute(string $action, Shop $shop, MetaMessageTemplate $metaMessageTemplate): array
    {
        return [
            'name'       => 'grp.org.shops.show.chat.whatsapp_templates.'.$action,
            'parameters' => [
                'organisation'        => $shop->organisation->slug,
                'shop'                => $shop->slug,
                'metaMessageTemplate' => $metaMessageTemplate->id,
            ],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request);
    }

    public function inDraft(Organisation $organisation, Shop $shop, MetaMessageTemplate $metaMessageTemplate, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request, $metaMessageTemplate);
    }
}
