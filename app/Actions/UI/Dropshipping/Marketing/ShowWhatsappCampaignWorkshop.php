<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\UI\Dropshipping\Marketing;

use App\Actions\Chat\Whatsapp\Templates\GetWhatsappTemplateTags;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaMessageTemplate;
use App\Enums\Comms\WhatsappCampaign\WhatsappCampaignStateEnum;
use App\Models\Comms\WhatsappCampaign;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWhatsappCampaignWorkshop extends OrgAction
{
    use WithMarketingEditAuthorisation;
    use WithWhatsappCampaignJourney;
    use WithWhatsappTemplatePreview;

    public function handle(WhatsappCampaign $campaign, ActionRequest $request): Response
    {
        $shop = $campaign->shop;

        return Inertia::render(
            'Org/Marketing/WhatsappCampaignWorkshop',
            [
                'breadcrumbs' => $this->getBreadcrumbs($campaign, $request->route()->originalParameters()),
                'title'       => $campaign->name,
                'pageHead'    => [
                    'title'      => $campaign->name,
                    'model'      => __('Campaign:'),
                    'modelStyle' => 'text-sm',
                    'titleStyle' => 'font-normal text-lg',
                    'icon'       => [
                        'icon'  => ['fab', 'fa-whatsapp'],
                        'title' => $campaign->name,
                    ],
                    'actions'    => [
                        [
                            'type'      => 'button',
                            'style'     => 'primary',
                            'icon'      => false,
                            'iconRight' => 'fal fa-arrow-right',
                            'label'     => __('Preview & send'),
                            'route'     => [
                                'name'       => preg_replace('/workshop$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],
                'journey'      => $this->getWhatsappCampaignJourney($campaign, 'compose'),
                'campaign'     => [
                    'name'                     => $campaign->name,
                    'meta_message_template_id' => $campaign->meta_message_template_id,
                    'recipients_count'         => $campaign->recipients_count,
                ],
                'updateRoute'  => [
                    'name'       => 'grp.org.shops.show.marketing.whatsapp_campaigns.update',
                    'parameters' => [
                        'organisation'     => $shop->organisation->slug,
                        'shop'             => $shop->slug,
                        'whatsappCampaign' => $campaign->slug,
                    ],
                    'method'     => 'patch',
                ],
                'recipientsRoute' => [
                    'name'       => 'grp.org.shops.show.marketing.whatsapp_campaigns.recipients.index',
                    'parameters' => [
                        'organisation'     => $shop->organisation->slug,
                        'shop'             => $shop->slug,
                        'whatsappCampaign' => $campaign->slug,
                    ],
                ],
                'createTemplateRoute' => [
                    'name'       => 'grp.org.shops.show.chat.whatsapp_templates.create',
                    'parameters' => [
                        'organisation' => $shop->organisation->slug,
                        'shop'         => $shop->slug,
                    ],
                ],
                'templates'    => $this->getTemplates($shop),
                'mergeTags'    => GetWhatsappTemplateTags::run($shop),
                'businessName' => $shop->name,
                'isConfigured' => filled(Arr::get($shop->settings, 'whatsapp.phone_number_id')),
                'isEditable'   => in_array($campaign->state, [WhatsappCampaignStateEnum::IN_PROCESS, WhatsappCampaignStateEnum::READY]),
            ]
        );
    }

    /**
     * The preview needs the template body/header/footer/buttons, not just its name, so the
     * whole set is sent up front and switched client side as the select changes.
     *
     * @return array<int, array{value: int, label: string, language: string, header: array|null, body: string|null, footer: string|null, buttons: array}>
     */
    protected function getTemplates(Shop $shop): array
    {
        return MetaMessageTemplate::where('shop_id', $shop->id)
            ->whereHas('metaChannel', function ($query) {
                $query->where('code', 'whatsapp');
            })
            ->where('status', 'APPROVED')
            ->orderBy('name')
            ->get()
            ->map(fn (MetaMessageTemplate $template) => $this->whatsappTemplatePreview($template))
            ->all();
    }

    public function asController(Organisation $organisation, Shop $shop, WhatsappCampaign $whatsappCampaign, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($whatsappCampaign, $request);
    }

    public function getBreadcrumbs(WhatsappCampaign $campaign, array $routeParameters): array
    {
        return array_merge(
            IndexWhatsappCampaigns::make()->getBreadcrumbs(Arr::only($routeParameters, ['organisation', 'shop'])),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => '#',
                        'label' => $campaign->name,
                    ],
                ],
            ],
        );
    }
}
