<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Http\Resources\Chat\StaffConversationResource;
use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowStaffMessaging extends OrgAction
{
    use AsAction;
    use WithInertia;

    protected ?StaffConversation $conversation = null;

    public function handle(Group $group): Group
    {
        return $group;
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function inConversation(StaffConversation $staffConversation, ActionRequest $request): Group
    {
        abort_unless($staffConversation->hasParticipant($request->user()), 403);

        $this->conversation = $staffConversation;
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $title        = __('Messaging');
        $conversation = $this->conversation;

        return Inertia::render(
            'Chat/StaffMessaging',
            [
                'breadcrumbs'    => $this->getBreadcrumbs($conversation),
                'title'          => $title,
                'pageHead'       => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-comments'],
                        'title' => $title,
                    ],
                ],
                'selected_ulid'  => $conversation?->ulid,
                'selected_conversation' => $conversation
                    ? (new StaffConversationResource($conversation->load('participants')))->resolve()
                    : null,
            ]
        );
    }

    public function getBreadcrumbs(?StaffConversation $conversation): array
    {
        $breadcrumbs = array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-comments',
                        'route' => [
                            'name' => 'grp.chat.staff.index',
                        ],
                        'label' => __('Messaging'),
                    ],
                ],
            ]
        );

        if ($conversation) {
            $breadcrumbs[] = [
                'type'   => 'simple',
                'simple' => [
                    'route' => [
                        'name'       => 'grp.chat.staff.show',
                        'parameters' => ['staffConversation' => $conversation->ulid],
                    ],
                    'label' => $conversation->name ?? __('Conversation'),
                ],
            ];
        }

        return $breadcrumbs;
    }
}
