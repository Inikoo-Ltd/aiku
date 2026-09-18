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
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatSession;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowOrgChatInbox extends OrgAction
{
    use AsAction;
    use WithInertia;

    private ?ChatSession $selectedSession = null;

    private ?int $preselectShopId = null;

    public function authorize(ActionRequest $request): bool
    {
        if ($request->user()->chatAgent) {
            return true;
        }

        if (isset($this->shop)) {
            return $request->user()->authTo([
                "crm.{$this->shop->id}.view",
                "accounting.{$this->shop->organisation_id}.view",
            ]);
        }

        return $request->user()->authTo([
            'accounting.'.$this->organisation->id.'.view',
            'org-supervisor.'.$this->organisation->id,
            'shops-view.'.$this->organisation->id,
        ]);
    }

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

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): Organisation
    {
        $this->preselectShopId = $shop->id;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(Organisation $organisation, ActionRequest $request): Response
    {
        $isReadOnly = !$request->user()->chatAgent?->shopAssignments()->exists();

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
                'is_read_only'         => $isReadOnly,
                'inboxes'              => $isReadOnly
                    ? $this->getReadOnlyInboxes($organisation, $request)
                    : $this->getAgentInboxes($organisation, $request),
                'selectedSessionUlid'  => $this->selectedSession ? (string) $this->selectedSession->ulid : null,
                'initialSession'       => $this->resolveSelectedSession(),
                'preselectShopId'      => $this->preselectShopId,
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

    /**
     * The website channel comes from the agent's real shop assignments; the WhatsApp channel is a
     * ponytail: static stub until chat sessions carry a channel and WhatsApp inboxes are modelled.
     *
     * @return array<int, array{id: int, name: string, slug: string, type: string|null, channels: array<int, array{key: string, name: string, unread: int}>}>
     */
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

        return $this->mapInboxes($shopsQuery->get());
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string, type: string|null, channels: array<int, array{key: string, name: string, unread: int}>}>
     */
    private function getReadOnlyInboxes(Organisation $organisation, ActionRequest $request): array
    {
        $shopsQuery = $request->user()->authorisedShops()
            ->where('shops.organisation_id', $organisation->id)
            ->where('shops.state', ShopStateEnum::OPEN)
            ->orderBy('shops.name');

        return $this->mapInboxes($shopsQuery->get());
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string, type: string|null, channels: array<int, array{key: string, name: string, unread: int}>}>
     */
    private function mapInboxes(Collection $shops): array
    {
        return $shops->map(function ($shop) {
            $channels = [
                ['key' => 'website', 'name' => __('Website'), 'unread' => 0],
            ];

            if (Arr::get($shop->settings, 'whatsapp.enabled', false)) {
                $channels[] = ['key' => 'whatsapp', 'name' => __('WhatsApp'), 'unread' => 0];
            }

            return [
                'id'       => $shop->id,
                'name'     => $shop->name,
                'slug'     => $shop->slug,
                'type'     => $shop->type?->value,
                'channels' => $channels,
            ];
        })->values()->all();
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
