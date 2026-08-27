<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates\UI;

use App\Actions\Chat\Whatsapp\Templates\GetWhatsappTemplateTags;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditWhatsappMessageTemplate extends OrgAction
{
    public function handle(Shop $shop, MetaMessageTemplate $metaMessageTemplate, ActionRequest $request): Response
    {
        $components = collect(Arr::get($metaMessageTemplate->data, 'components', []));
        $header     = $components->firstWhere('type', 'HEADER');

        return Inertia::render(
            'Org/Chat/EditWhatsappTemplate',
            [
                'breadcrumbs' => IndexWhatsappMessageTemplates::make()->getBreadcrumbs([
                    'organisation' => $shop->organisation->slug,
                    'shop'         => $shop->slug,
                ]),
                'title'    => $metaMessageTemplate->name,
                'pageHead' => [
                    'title' => $metaMessageTemplate->name,
                    'icon'  => [
                        'icon'  => ['fab', 'fa-whatsapp'],
                        'title' => $metaMessageTemplate->name,
                    ],
                ],
                'template' => [
                    'id'            => $metaMessageTemplate->id,
                    'name'          => $metaMessageTemplate->name,
                    'label'         => Arr::get($metaMessageTemplate->data ?? [], 'label', ''),
                    'language'      => $metaMessageTemplate->language,
                    'category'      => $metaMessageTemplate->category,
                    'status'        => $metaMessageTemplate->status,
                    'rejected_reason' => Arr::get($metaMessageTemplate->data ?? [], 'rejected_reason'),
                    'header_format' => Arr::get($header, 'format'),
                    'header_text'   => Arr::get($header, 'text'),
                    'body'          => Arr::get($components->firstWhere('type', 'BODY') ?? [], 'text', ''),
                    'footer'        => Arr::get($components->firstWhere('type', 'FOOTER') ?? [], 'text'),
                    'buttons'       => Arr::get($components->firstWhere('type', 'BUTTONS') ?? [], 'buttons', []),
                    'merge_tags'    => Arr::get($metaMessageTemplate->data ?? [], 'merge_tags.body', []),
                    'synchronize_at' => $metaMessageTemplate->synchronize_at,
                ],
                'mergeTags'    => GetWhatsappTemplateTags::run($shop),
                'businessName' => $shop->name,
                'updateRoute'  => $this->route('update', $shop, $metaMessageTemplate),
                'variablesRoute' => $this->route('variables', $shop, $metaMessageTemplate),
                'deleteRoute'  => $this->route('delete', $shop, $metaMessageTemplate),
            ]
        );
    }

    /**
     * @return array{name: string, parameters: array<string, mixed>}
     */
    protected function route(string $action, Shop $shop, MetaMessageTemplate $metaMessageTemplate): array
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

    public function asController(Organisation $organisation, Shop $shop, MetaMessageTemplate $metaMessageTemplate, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $metaMessageTemplate, $request);
    }
}
