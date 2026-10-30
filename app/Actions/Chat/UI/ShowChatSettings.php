<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\UI;

use App\Actions\Chat\Agent\UI\IndexAgent;
use App\Actions\Chat\ChatSession\SendOutOfHoursReply;
use App\Actions\Chat\UpdateShopChatClosing;
use App\Actions\Chat\EmailContact\UI\IndexChatEmailContacts;
use App\Actions\Chat\UpdateShopEmailFooters;
use App\Http\Resources\Chat\ChatEmailContactResource;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\Chat\WithChatScopeNavigation;
use App\Actions\Comms\Mailbox\ProcessInboundEmail;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Actions\Chat\Whatsapp\Templates\GetWhatsappTemplateTags;
use App\Actions\Chat\Whatsapp\Templates\UI\IndexWhatsappMessageTemplates;
use App\Actions\OrgAction;
use App\Enums\UI\Chat\ChatSettingsTabsEnum;
use App\Http\Resources\Chat\MetaMessageTemplatesResource;
use App\Http\Resources\CRM\Livechat\ChatAgentResource;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatKnowledgeEntry;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowChatSettings extends OrgAction
{
    use WithChatScopeNavigation;
    use WithChatAgentAuthorisation;

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
        $contactsTab    = ChatSettingsTabsEnum::EMAIL_CONTACTS->value;
        $indexContacts  = IndexChatEmailContacts::make();
        $contacts       = fn () => ChatEmailContactResource::collection($indexContacts->handle($parent, $contactsTab));

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
                    'actions' => $this->getActions($isShop, $request),
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => $isShop ? ChatSettingsTabsEnum::navigationExcept([ChatSettingsTabsEnum::COURIERS]) : ChatSettingsTabsEnum::navigationExcept([ChatSettingsTabsEnum::OUT_OF_HOURS, ChatSettingsTabsEnum::EMAIL_FOOTER, ChatSettingsTabsEnum::EMAIL_CONTACTS, ChatSettingsTabsEnum::CLOSING, ChatSettingsTabsEnum::POLICIES]),
                ],
                'settingsRoute'  => $this->chatRoute('settings'),
                'templatesTable' => $isShop ? $this->getShopTemplatesTableProps($request) : null,
                'outOfHours'     => $isShop ? $this->getOutOfHoursProps($parent) : null,
                'emailFooters'   => $isShop ? [
                    'footers'      => collect((array) data_get($parent->settings, 'chat.email_footers', []))
                        ->map(fn (string $text, string $language) => ['language' => $language === UpdateShopEmailFooters::DEFAULT_KEY ? null : $language, 'text' => $text])
                        ->values()
                        ->all(),
                    'can_edit'     => $this->userCanActOnChatOnShop($request->user(), $parent),
                    'update_route' => [
                        'name'       => 'grp.org.shops.show.chat.settings.email_footers.update',
                        'parameters' => ['organisation' => $this->organisation->slug, 'shop' => $parent->slug],
                    ],
                ] : null,
                'emailContacts'  => $isShop ? [
                    'can_manage_shop'      => $this->userCanActOnChatOnShop($request->user(), $parent),
                    'can_manage_all_shops' => $this->userCanWorkChatOnOrganisation($request->user(), $this->organisation),
                    'shop_id'              => $parent->id,
                    'organisation'         => $this->organisation->slug,
                ] : null,
                'closing'        => $isShop ? [
                    'close_after_thanks'         => UpdateShopChatClosing::isOn($parent),
                    'wait_for_customer_hours'    => UpdateShopChatClosing::waitingHours($parent),
                    'can_edit'                   => $this->userSupervisesChatOnShop($request->user(), $parent),
                    'update_route'               => [
                        'name'       => 'grp.org.shops.show.chat.settings.closing.update',
                        'parameters' => ['organisation' => $this->organisation->slug, 'shop' => $parent->slug],
                    ],
                ] : null,
                'policies'       => $isShop ? [
                    'text'         => data_get($parent->settings, 'chat.policies', ''),
                    'notes'        => ChatKnowledgeEntry::where('shop_id', $parent->id)->where('is_manual', true)->latest('updated_at')->get(['id', 'title', 'body', 'updated_at'])->all(),
                    'copied'       => ChatKnowledgeEntry::where('shop_id', $parent->id)->whereIn('source_type', ['webpage', 'shop_settings', 'docs'])->selectRaw('kind, count(*) as total, max(hydrated_at) as at')->groupBy('kind')->get()->all(),
                    'learned'      => ChatKnowledgeEntry::where('shop_id', $parent->id)->where('source_type', 'learned')->whereIn('status', ['active', 'proposed', 'conflict'])->orderByDesc('customers_count')->limit(200)
                        ->get(['id', 'title', 'body', 'status', 'conflict', 'customers_count', 'last_seen_at', 'expires_at'])->all(),
                    'knowledge_route' => ['organisation' => $this->organisation->slug, 'shop' => $parent->slug],
                    'can_edit'        => $this->userCanActOnChatOnShop($request->user(), $parent),
                    'update_route' => [
                        'name'       => 'grp.org.shops.show.chat.settings.policies.update',
                        'parameters' => ['organisation' => $this->organisation->slug, 'shop' => $parent->slug],
                    ],
                ] : null,
                'couriers'       => $isShop ? null : $this->getCouriersProps($request),

                $agentsTab => $this->tab == $agentsTab ? $agents : Inertia::lazy($agents),

                $templatesTab => $this->tab == $templatesTab ? $templates : Inertia::lazy($templates),

                ...($isShop ? [$contactsTab => $this->tab == $contactsTab ? $contacts : Inertia::lazy($contacts)] : []),
            ]
        )->table(
            $indexAgent->tableStructure(parent: $parent, prefix: $agentsTab)
        )->table(
            $indexTemplates->tableStructure(prefix: $templatesTab, withShopColumn: !$isShop)
        )->table(
            $indexContacts->tableStructure(prefix: $contactsTab)
        );
    }

    private function getActions(bool $isShop, ActionRequest $request): array
    {
        if ($this->tab == ChatSettingsTabsEnum::AGENTS->value) {
            if (!$this->userSupervisesChatOnOrganisation($request->user(), $this->organisation)) {
                return [];
            }

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

        if (!$isShop || in_array($this->tab, [ChatSettingsTabsEnum::OUT_OF_HOURS->value, ChatSettingsTabsEnum::CLOSING->value, ChatSettingsTabsEnum::POLICIES->value], true)) {
            return [];
        }

        $shopParameters = [
            'organisation' => $this->organisation->slug,
            'shop'         => $this->shop->slug,
        ];

        $canCreateTemplate = $request->user()->authTo([
            "chat-m.{$this->shop->id}",
            "crm.{$this->shop->id}.edit",
            "org-admin.{$this->organisation->id}",
        ]);

        return array_values(array_filter([
            $canCreateTemplate ? [
                'type'  => 'button',
                'style' => 'primary',
                'icon'  => 'fal fa-plus',
                'label' => __('New template'),
                'route' => [
                    'method'     => 'get',
                    'name'       => 'grp.org.shops.show.chat.whatsapp_templates.create',
                    'parameters' => $shopParameters,
                ],
            ] : null,
            $canCreateTemplate ? [
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
            ] : null,
        ]));
    }

    private function getCouriersProps(ActionRequest $request): array
    {
        $domains = ProcessInboundEmail::carrierDomains($this->group);

        $sessionsBySenderDomain = DB::table('chat_sessions')
            ->join('shops', 'shops.id', '=', 'chat_sessions.shop_id')
            ->where('shops.group_id', $this->group->id)
            ->where('chat_sessions.is_carrier', true)
            ->where('chat_sessions.status', '!=', ChatSessionStatusEnum::CLOSED->value)
            ->where('chat_sessions.created_at', '>=', now()->subDays(30))
            ->selectRaw("lower(split_part(chat_sessions.metadata->>'email_from', '@', 2)) as domain, count(*) as sessions")
            ->groupBy('domain')
            ->pluck('sessions', 'domain');

        return [
            'domains'      => collect($domains)->map(fn (string $domain) => [
                'domain'   => $domain,
                'sessions' => $sessionsBySenderDomain
                    ->filter(fn ($count, $senderDomain) => $senderDomain === $domain || str_ends_with((string) $senderDomain, '.'.$domain))
                    ->sum(),
            ])->all(),
            'can_edit'     => $this->userSupervisesChatOnOrganisation($request->user(), $this->organisation),
            'update_route' => [
                'name'       => 'grp.org.chat.settings.carrier_domains.update',
                'parameters' => ['organisation' => $this->organisation->slug],
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

    private function getShopTemplatesTableProps(ActionRequest $request): array
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
            'canEdit'           => $request->user()->authTo([
                "chat-m.{$this->shop->id}",
                "crm.{$this->shop->id}.edit",
                "org-admin.{$this->organisation->id}",
            ]),
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
