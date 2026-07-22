<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Http\Resources\CRM\Livechat\ChatSessionListResource;
use App\Models\Chat\ChatSession;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowOrgChatInbox extends OrgAction
{
    use AsAction;
    use WithInertia;

    private ?ChatSession $selectedSession = null;

    public function handle(Organisation $organisation): Organisation
    {
        return $organisation;
    }

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function inConversation(Organisation $organisation, ChatSession $chatSession, ActionRequest $request): Organisation
    {
        $this->selectedSession = $chatSession;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(Organisation $organisation, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Chat/Inbox',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Inbox'),
                'pageHead'    => [
                    'title' => __('Inbox'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-inbox'],
                        'title' => __('Inbox'),
                    ],
                ],
                'organisation' => [
                    'id'   => $organisation->id,
                    'slug' => $organisation->slug,
                    'name' => $organisation->name,
                ],
                'inboxes'              => $this->getAgentInboxes($organisation, $request),
                'selectedSessionUlid'  => $this->selectedSession ? (string) $this->selectedSession->ulid : null,
                'initialSession'       => $this->resolveSelectedSession(),
            ]
        );
    }

    private function resolveSelectedSession(): ?array
    {
        if (!$this->selectedSession) {
            return null;
        }

        $this->selectedSession->loadMissing([
            'messages' => fn ($q) => $q->latest()->limit(1),
            'chatEvents' => fn ($q) => $q->where('event_type', ChatEventTypeEnum::GUEST_PROFILE)->latest()->limit(1),
            'webUser',
            'shop',
            'assignments.chatAgent.user',
        ]);

        return (new ChatSessionListResource($this->selectedSession))->resolve();
    }

    private function getAgentInboxes(Organisation $organisation, ActionRequest $request): array
    {
        $agent = $request->user()?->chatAgent;

        if (!$agent) {
            return [];
        }

        $assignments = $agent->shopAssignments()
            ->where('organisation_id', $organisation->id)
            ->get();

        if ($assignments->isEmpty()) {
            return [];
        }

        $shopsQuery = $organisation->shops()
            ->where('state', ShopStateEnum::OPEN)
            ->orderBy('name');

        $isOrgWide = $assignments->contains(fn ($assignment) => $assignment->shop_id === null);

        if (!$isOrgWide) {
            $shopsQuery->whereIn('id', $assignments->pluck('shop_id')->filter());
        }

        return $shopsQuery->get()->map(fn ($shop) => [
            'id'   => $shop->id,
            'name' => $shop->name,
            'slug' => $shop->slug,
            'type' => $shop->type?->value,
        ])->values()->all();
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-inbox',
                        'route' => [
                            'name'       => 'grp.org.chat.inbox',
                            'parameters' => ['organisation' => $routeParameters['organisation'] ?? null],
                        ],
                        'label' => __('Inbox'),
                    ],
                ],
            ]
        );
    }
}
