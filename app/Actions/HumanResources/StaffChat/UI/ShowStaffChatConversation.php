<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\StaffChat\UI;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\GetStaffChatAnalytics;
use App\Actions\SysAdmin\UI\IndexStaffChatAnalytics;
use App\Http\Resources\Chat\StaffMessageResource;
use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStaffChatConversation extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            "human-resources.{$this->organisation->id}.view",
            "org-supervisor.{$this->organisation->id}.human-resources",
            "org-admin.{$this->organisation->id}",
        ]);
    }

    public function handle(StaffConversation $conversation): StaffConversation
    {
        return $conversation;
    }

    public function asController(Organisation $organisation, StaffConversation $staffConversation, ActionRequest $request): StaffConversation
    {
        $this->initialisation($organisation, $request);

        $inScope = StaffConversation::whereKey($staffConversation->id)
            ->where('group_id', $organisation->group_id)
            ->tap(fn ($query) => GetStaffChatAnalytics::scopeToOrganisation($query, $organisation))
            ->exists();
        abort_unless($inScope, 404);

        return $this->handle($staffConversation);
    }

    public function htmlResponse(StaffConversation $conversation, ActionRequest $request): Response
    {
        $members = $conversation->participants()->get()
            ->map(fn ($user) => $user->chatName())
            ->implode(', ');

        $messages = $conversation->messages()
            ->with(['user', 'translations', 'reactions', 'conversation'])
            ->orderBy('id')
            ->limit(1000)
            ->get();

        return Inertia::render(
            'Org/HumanResources/StaffChatConversation',
            [
                'breadcrumbs' => $this->getBreadcrumbs($conversation, $request->route()->originalParameters()),
                'title'       => __('Staff chat'),
                'pageHead'    => [
                    'icon'  => ['icon' => ['fal', 'fa-comments-alt'], 'title' => __('Staff chat')],
                    'title' => $conversation->name ?: $members,
                    'model' => $conversation->context_type ? class_basename($conversation->context_type) : __('Conversation'),
                ],
                'conversation' => [
                    'ulid'            => $conversation->ulid,
                    'type'            => $conversation->type,
                    'context'         => $conversation->context_type ? class_basename($conversation->context_type) : null,
                    'members'         => $members,
                    'created_at'      => $conversation->created_at,
                    'last_message_at' => $conversation->last_message_at,
                ],
                'messages' => StaffMessageResource::collection($messages)->resolve(),
            ]
        );
    }

    public function getBreadcrumbs(StaffConversation $conversation, array $routeParameters): array
    {
        $index = IndexStaffChatAnalytics::make();
        $index->scopedOrganisation = $this->organisation;

        return array_merge(
            $index->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.hr.staff_chat.show',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $conversation->name ?: __('Conversation'),
                    ]
                ]
            ]
        );
    }
}
