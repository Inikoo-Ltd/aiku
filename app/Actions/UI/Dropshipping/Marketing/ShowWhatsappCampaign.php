<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\UI\Dropshipping\Marketing;

use App\Actions\Chat\Whatsapp\Templates\GetWhatsappTemplateTags;
use App\Actions\Helpers\TimeZone\UI\GetTimeZoneSelectOptions;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Enums\Comms\WhatsappCampaign\WhatsappCampaignStateEnum;
use App\Enums\UI\Marketing\WhatsappCampaignTabsEnum;
use App\Http\Resources\Comms\WhatsappCampaignSentRecipientsResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WhatsappCampaign;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWhatsappCampaign extends OrgAction
{
    use WithMarketingEditAuthorisation;
    use WithWhatsappCampaignJourney;
    use WithWhatsappCampaignTimeline;
    use WithWhatsappTemplatePreview;

    public function handle(WhatsappCampaign $campaign, ActionRequest $request): Response
    {
        $shop     = $campaign->shop;
        $template = $campaign->metaMessageTemplate;

        $routeParameters = [
            'organisation'     => $shop->organisation->slug,
            'shop'             => $shop->slug,
            'whatsappCampaign' => $campaign->slug,
        ];

        $routeBase = 'grp.org.shops.show.marketing.whatsapp_campaigns';

        $hasBeenSent = in_array($campaign->state, [
            WhatsappCampaignStateEnum::SENDING,
            WhatsappCampaignStateEnum::SENT,
        ]);

        $recipientsPrefix = WhatsappCampaignTabsEnum::RECIPIENTS->value;

        $recipients = fn () => WhatsappCampaignSentRecipientsResource::collection(
            IndexWhatsappCampaignSentRecipients::run($campaign, $recipientsPrefix)
        );

        $response = Inertia::render(
            'Org/Marketing/WhatsappCampaign',
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
                ],
                'journey'      => $this->getWhatsappCampaignJourney($campaign, 'review'),
                'timeline'     => $this->getWhatsappCampaignTimeline($campaign),
                'stats'        => $this->getWhatsappCampaignStats($campaign),
                'tabs'         => [
                    'current'    => $this->tab,
                    'navigation' => WhatsappCampaignTabsEnum::navigation($campaign),
                ],
                $recipientsPrefix => $hasBeenSent
                    ? ($this->tab == $recipientsPrefix ? $recipients : Inertia::optional($recipients))
                    : null,
                'campaign'     => [
                    'name'             => $campaign->name,
                    'state'            => $campaign->state,
                    'state_label'      => $campaign->state->labels()[$campaign->state->value],
                    'recipients_count' => $campaign->recipients_count,
                    'scheduled_at'     => $campaign->scheduled_at?->toIso8601String(),
                    'sent_at'          => $campaign->sent_at?->toIso8601String(),
                ],
                'status'       => $campaign->state->value,
                'template'     => $template ? $this->whatsappTemplatePreview($template) : null,
                'workshopRoute' => [
                    'name'       => "$routeBase.workshop",
                    'parameters' => $routeParameters,
                ],
                'sendRoute'           => [
                    'name'       => "$routeBase.send",
                    'parameters' => $routeParameters,
                ],
                'scheduleRoute'       => [
                    'name'       => "$routeBase.schedule",
                    'parameters' => $routeParameters,
                ],
                'cancelScheduleRoute' => [
                    'name'       => "$routeBase.cancel-schedule",
                    'parameters' => $routeParameters,
                ],
                'deleteRoute'         => [
                    'name'       => "$routeBase.delete",
                    'parameters' => $routeParameters,
                    'method'     => 'delete',
                ],
                'isDeletable'         => $campaign->isUnsent(),
                'isConfigured'        => filled(Arr::get($shop->settings, 'whatsapp.phone_number_id')),
                'timeZoneOptions'     => GetTimeZoneSelectOptions::run(),
                'defaultShopTimezone' => $shop->timezone?->name ?? 'UTC',
                'mergeTags'      => GetWhatsappTemplateTags::run($shop),
                'businessName'   => $shop->name,
                'inboxRoute'     => [
                    'name'       => 'grp.org.shops.show.chat.inbox',
                    'parameters' => Arr::only($routeParameters, ['organisation', 'shop']),
                ],
            ]
        );

        if (!$hasBeenSent) {
            return $response;
        }

        return $response->table(
            IndexWhatsappCampaignSentRecipients::make()->tableStructure($recipientsPrefix, $campaign)
        );
    }

    public function asController(Organisation $organisation, Shop $shop, WhatsappCampaign $whatsappCampaign, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request)->withTab(WhatsappCampaignTabsEnum::values());

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
