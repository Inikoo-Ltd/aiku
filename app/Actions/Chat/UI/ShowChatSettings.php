<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\UI;

use App\Actions\Chat\Agent\UI\IndexAgent;
use App\Actions\Chat\GetChatCapabilities;
use App\Actions\Chat\GetChatScopeShops;
use App\Actions\Chat\Whatsapp\Templates\UI\IndexWhatsappMessageTemplates;
use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Enums\UI\Chat\ChatSettingsTabsEnum;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowChatSettings extends OrgAction
{
    use AsAction;
    use WithInertia;

    public function authorize(ActionRequest $request): bool
    {
        return GetChatCapabilities::run($request->user())['can_view'];
    }

    public function handle(Group $group): Group
    {
        return $group;
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(app('group'), $request)->withTab(ChatSettingsTabsEnum::values());

        return $this->handle($this->group);
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $indexAgent     = IndexAgent::make();
        $indexTemplates = IndexWhatsappMessageTemplates::make();
        $shopIds        = GetChatScopeShops::make()->shopIds($request->user());

        return Inertia::render(
            'Grp/Chat/ChatSettings',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Chat settings'),
                'pageHead'    => [
                    'title' => __('Chat settings'),
                    'icon'  => [
                        'title' => __('Chat settings'),
                        'icon'  => ['fal', 'fa-sliders-h'],
                    ],
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => ChatSettingsTabsEnum::navigation(),
                ],
                'can_manage_agents' => GetChatCapabilities::run($request->user())['is_supervisor'],

                ChatSettingsTabsEnum::AGENTS->value => $this->tab == ChatSettingsTabsEnum::AGENTS->value
                    ? fn () => $indexAgent->handle($group, ChatSettingsTabsEnum::AGENTS->value)
                    : Inertia::lazy(fn () => $indexAgent->handle($group, ChatSettingsTabsEnum::AGENTS->value)),

                ChatSettingsTabsEnum::WHATSAPP_TEMPLATES->value => $this->tab == ChatSettingsTabsEnum::WHATSAPP_TEMPLATES->value
                    ? fn () => $indexTemplates->inChatScope($shopIds, ChatSettingsTabsEnum::WHATSAPP_TEMPLATES->value)
                    : Inertia::lazy(fn () => $indexTemplates->inChatScope($shopIds, ChatSettingsTabsEnum::WHATSAPP_TEMPLATES->value)),
            ],
        )->table(
            $indexAgent->tableStructure(prefix: ChatSettingsTabsEnum::AGENTS->value)
        )->table(
            $indexTemplates->tableStructure(prefix: ChatSettingsTabsEnum::WHATSAPP_TEMPLATES->value, withShopColumn: true)
        );
    }

    public function getBreadcrumbs(): array
    {
        return [
            [
                'type'   => 'simple',
                'simple' => [
                    'route' => ['name' => 'grp.chat.settings'],
                    'label' => __('Chat settings'),
                    'icon'  => 'fal fa-sliders-h',
                ],
            ],
        ];
    }
}
