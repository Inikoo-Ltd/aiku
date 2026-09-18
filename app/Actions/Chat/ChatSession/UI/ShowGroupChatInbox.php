<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession\UI;

use App\Actions\Chat\GetChatCapabilities;
use App\Actions\Chat\GetChatScopeShops;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Http\Resources\CRM\Livechat\ChatSessionListResource;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatSession;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowGroupChatInbox extends OrgAction
{
    use AsAction;
    use WithInertia;

    private ?ChatSession $selectedSession = null;

    private array $capabilities = [];

    public function authorize(ActionRequest $request): bool
    {
        $this->capabilities = GetChatCapabilities::run($request->user());

        return $this->capabilities['can_view'];
    }

    public function handle(Group $group): Group
    {
        return $group;
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function inConversation(ChatSession $chatSession, ActionRequest $request): Group
    {
        $this->selectedSession = $chatSession;
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $inboxes = $this->getInboxes($request->user());

        return Inertia::render(
            'Org/Chat/Inbox',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Inbox'),
                'pageHead'    => [
                    'title' => __('Inbox'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-inbox'],
                        'title' => __('Inbox'),
                    ],
                ],
                'organisation'        => Arr::get($inboxes, '0.organisation'),
                'inboxes'             => $inboxes,
                'is_read_only'        => $this->capabilities['is_read_only'],
                'selectedSessionUlid' => $this->selectedSession ? (string) $this->selectedSession->ulid : null,
                'initialSession'      => $this->resolveSelectedSession(),
                'preselectShopId'     => null,
            ]
        );
    }

    private function resolveSelectedSession(): ?array
    {
        if (!$this->selectedSession) {
            return null;
        }

        $this->selectedSession->loadMissing([
            'messages'   => fn ($q) => $q->latest()->limit(1),
            'chatEvents' => fn ($q) => $q->where('event_type', ChatEventTypeEnum::GUEST_PROFILE)->latest()->limit(1),
            'webUser',
            'shop',
            'assignments.chatAgent.user',
        ]);

        return (new ChatSessionListResource($this->selectedSession))->resolve();
    }

    /**
     * Agents get the shops they are assigned to; everyone else reads the shops their own
     * authorisation already covers, so a supervisor sees the whole desk without holding a
     * chat agent profile. Every inbox carries its own organisation because one screen now
     * spans all of them.
     *
     * @return array<int, array{id: int, name: string, slug: string, type: string|null, organisation: array{id: int, slug: string, name: string}, channels: array<int, array{key: string, name: string, unread: int}>}>
     */
    /**
     * Every inbox carries its own organisation because one screen now spans all of them.
     *
     * @return array<int, array{id: int, name: string, slug: string, type: string|null, organisation: array{id: int|null, slug: string|null, name: string|null}, channels: array<int, array{key: string, name: string, unread: int}>}>
     */
    private function getInboxes(?User $user): array
    {
        return GetChatScopeShops::run($user)
            ->sortBy(fn (Shop $shop) => $shop->organisation?->name.' '.$shop->name)
            ->map(function (Shop $shop) {
                $channels = [
                    ['key' => 'website', 'name' => __('Website'), 'unread' => 0],
                ];

                if (Arr::get($shop->settings, 'whatsapp.enabled', false)) {
                    $channels[] = ['key' => 'whatsapp', 'name' => __('WhatsApp'), 'unread' => 0];
                }

                return [
                    'id'           => $shop->id,
                    'name'         => $shop->name,
                    'slug'         => $shop->slug,
                    'type'         => $shop->type?->value,
                    'organisation' => [
                        'id'   => $shop->organisation?->id,
                        'slug' => $shop->organisation?->slug,
                        'name' => $shop->organisation?->name,
                    ],
                    'channels'     => $channels,
                ];
            })->values()->all();
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-inbox',
                        'route' => ['name' => 'grp.chat.inbox'],
                        'label' => __('Inbox'),
                    ],
                ],
            ]
        );
    }
}
