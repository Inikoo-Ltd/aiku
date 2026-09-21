<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\PhoneCall\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Http\Resources\CRM\Livechat\ChatPhoneCallResource;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowGroupChatPhoneCalls extends OrgAction
{
    use AsAction;
    use WithInertia;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
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

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $index = IndexChatPhoneCalls::make();
        $calls = $index->handle($group, 'phone_calls', $request->user());

        return Inertia::render(
            'Chat/PhoneCalls',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Phone calls'),
                'pageHead'    => [
                    'title' => __('Phone calls'),
                    'icon'  => [
                        'title' => __('Phone calls'),
                        'icon'  => ['fal', 'fa-phone'],
                    ],
                ],
                'data' => ChatPhoneCallResource::collection($calls),
            ]
        )->table($index->tableStructure($group, 'phone_calls', $request->user()));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-phone',
                        'route' => [
                            'name'       => 'grp.chat.phone_calls.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Phone calls'),
                    ],
                ],
            ]
        );
    }
}
