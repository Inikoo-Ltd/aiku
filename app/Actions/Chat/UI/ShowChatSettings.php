<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\UI;

use App\Actions\Chat\Agent\UI\IndexAgent;
use App\Actions\Chat\ChatSession\SendOutOfHoursReply;
use App\Actions\Chat\WithChatScopeNavigation;
use App\Actions\Chat\Whatsapp\Templates\GetWhatsappTemplateTags;
use App\Actions\Chat\Whatsapp\Templates\UI\IndexWhatsappMessageTemplates;
use App\Actions\OrgAction;
use App\Enums\UI\Chat\ChatSettingsTabsEnum;
use App\Http\Resources\Chat\MetaMessageTemplatesResource;
use App\Http\Resources\CRM\Livechat\ChatAgentResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowChatSettings extends OrgAction
{
    use WithChatScopeNavigation;

    public function authorize(ActionRequest $request): bool
    {
        if ($request->user()->chatAgent) {
            return true;
        }

        if (isset($this->shop)) {
            return $request->user()->authTo([
                "chat.{$this->shop->id}.view",
                "accounting.{$this->shop->organisation_id}.view",
            ]);
        }

        return $request->user()->authTo([
            'accounting.'.$this->organisation->id.'.view',
            'org-supervisor.'.$this->organisation->id,
            'shops-view.'.$this->organisation->id,
        ]);
    }

    public function handle(Organisation|Shop $parent): Organisation|Shop
    {
        return $parent;
    }

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request)->withTab(ChatSettingsTabsEnum::values());

        return $this->handle($organisation);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request)->withTab(ChatSettingsTabsEnum::values());

        return $this->handle($shop);
    }

    public function htmlResponse(Organisation|Shop $parent, ActionRequest $request): Response
    {
        $indexAgent     = IndexAgent::make();
        $indexTemplates = IndexWhatsappMessageTemplates::make();
        $isShop         = $parent instanceof Shop;
        $agentsTab      = ChatSettingsTabsEnum::AGENTS->value;
        $templatesTab   = ChatSettingsTabsEnum::WHATSAPP_TEMPLATES->value;

        $agents    = fn () => ChatAgentResource::collection($indexAgent->handle($parent, $agentsTab));
        $templates = fn () => MetaMessageTemplatesResource::collection(
            $isShop
                ? $indexTemplates->handle($parent, $templatesTab)
                : $indexTemplates->inOrganisationScope($parent, $templatesTab)
        );

        return Inertia::render(
            'Org/Chat/ChatSettings',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Chat settings'),
                'pageHead'    => [
                    'title'   => __('Chat settings'),
                    'icon'    => [
                        'title' => __('Chat settings'),
                        'icon'  => ['fal', 'fa-sliders-h'],
                    ],
                    'actions' => $this->getActions($isShop),
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => $isShop ? ChatSettingsTabsEnum::navigation() : ChatSettingsTabsEnum::navigationExcept([ChatSettingsTabsEnum::OUT_OF_HOURS, ChatSettingsTabsEnum::POLICIES]),
                ],
                'settingsRoute'  => $this->chatRoute('settings'),
                'templatesTable' => $isShop ? $this->getShopTemplatesTableProps() : null,
                'outOfHours'     => $isShop ? $this->getOutOfHoursProps($parent) : null,
                'policies'       => $isShop ? [
                    'text'         => data_get($parent->settings, 'chat.policies', ''),
                    'update_route' => [
                        'name'       => 'grp.org.shops.show.chat.settings.policies.update',
                        'parameters' => ['organisation' => $this->organisation->slug, 'shop' => $parent->slug],
                    ],
                ] : null,

                $agentsTab => $this->tab == $agentsTab ? $agents : Inertia::lazy($agents),

                $templatesTab => $this->tab == $templatesTab ? $templates : Inertia::lazy($templates),
            ]
        )->table(
            $indexAgent->tableStructure(parent: $parent, prefix: $agentsTab)
        )->table(
            $indexTemplates->tableStructure(prefix: $templatesTab, withShopColumn: !$isShop)
        );
    }

    private function getActions(bool $isShop): array
    {
        if ($this->tab == ChatSettingsTabsEnum::AGENTS->value) {
            return [
                [
                    'type'    => 'button',
                    'style'   => 'create',
                    'tooltip' => __('Create CRM Agent'),
                    'label'   => __('Create CRM Agent'),
                    'route'   => [
                        'name'       => 'grp.org.chat.agents.create',
                        'parameters' => [$this->organisation->slug],
                    ],
                ],
            ];
        }

        if (!$isShop || $this->tab == ChatSettingsTabsEnum::OUT_OF_HOURS->value) {
            return [];
        }

        $shopParameters = [
            'organisation' => $this->organisation->slug,
            'shop'         => $this->shop->slug,
        ];

        return [
            [
                'type'  => 'button',
                'style' => 'primary',
                'icon'  => 'fal fa-plus',
                'label' => __('New template'),
                'route' => [
                    'method'     => 'get',
                    'name'       => 'grp.org.shops.show.chat.whatsapp_templates.create',
                    'parameters' => $shopParameters,
                ],
            ],
            [
                'type'        => 'button',
                'style'       => 'tertiary',
                'icon'        => ['fab', 'fa-whatsapp'],
                'tooltip'     => __('Fetch the message templates from Meta'),
                'label'       => __('Synchronize'),
                'fullLoading' => true,
                'route'       => [
                    'method'     => 'post',
                    'name'       => 'grp.org.shops.show.chat.whatsapp_templates.sync',
                    'parameters' => $shopParameters,
                ],
            ],
        ];
    }

    private function getOutOfHoursProps(Shop $shop): array
    {
        return [
            'message'      => data_get($shop->settings, 'chat.out_of_hours_message', ''),
            'opening_line' => SendOutOfHoursReply::make()->text($shop, true, null, true),
            'show_opening_line' => data_get($shop->settings, 'chat.out_of_hours_opening_line') !== false,
            'update_route' => [
                'name'       => 'grp.org.shops.show.chat.settings.out_of_hours_message.update',
                'parameters' => [
                    'organisation' => $this->organisation->slug,
                    'shop'         => $shop->slug,
                ],
            ],
        ];
    }

    private function getShopTemplatesTableProps(): array
    {
        $shopParameters = [
            'organisation' => $this->organisation->slug,
            'shop'         => $this->shop->slug,
        ];

        return [
            'mergeTags'         => GetWhatsappTemplateTags::run($this->shop),
            'editRouteName'     => 'grp.org.shops.show.chat.whatsapp_templates.edit',
            'deleteRouteName'   => 'grp.org.shops.show.chat.whatsapp_templates.delete',
            'refreshRouteName'  => 'grp.org.shops.show.chat.whatsapp_templates.refresh',
            'draftRouteName'    => 'grp.org.shops.show.chat.whatsapp_templates.draft.edit',
            'languageRouteName' => 'grp.org.shops.show.chat.whatsapp_templates.language',
            'routeParameters'   => $shopParameters,
            'variablesRoute'    => [
                'name'       => 'grp.org.shops.show.chat.whatsapp_templates.variables',
                'parameters' => $shopParameters,
            ],
        ];
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            $this->chatParentBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-sliders-h',
                        'route' => $this->chatRoute('settings'),
                        'label' => __('Chat settings'),
                    ],
                ],
            ]
        );
    }
}
