<?php

namespace App\Actions\CRM\ChatAutomation\UI;

use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Http\Resources\CRM\Livechat\ChatAutomationResource;
use App\Models\CRM\Livechat\ChatAutomation;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class EditChatAutomation extends OrgAction
{
    use AsAction;
    use WithInertia;

    public function handle(ChatAutomation $chatAutomation): ChatAutomation
    {
        return $chatAutomation;
    }

    public function asController(Organisation $organisation, ChatAutomation $chatAutomation, ActionRequest $request): ChatAutomation
    {
        $this->initialisation($organisation, $request);

        return $this->handle($chatAutomation);
    }

    public function htmlResponse(ChatAutomation $chatAutomation, ActionRequest $request): Response
    {
        $organisation = $this->organisation;
        $chatAutomation->load('shop');

        return Inertia::render(
            'Org/Chat/AutomationForm',
            [
                'breadcrumbs'  => ShowChatAutomations::make()->getBreadcrumbs($request->route()->originalParameters()),
                'title'        => __('Edit Automated Message'),
                'pageHead'     => [
                    'title'   => $chatAutomation->name,
                    'model'   => __('Automated Message'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-robot'],
                        'title' => __('Edit Automated Message'),
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.chat.automations.show',
                                'parameters' => ['organisation' => $organisation->slug],
                            ],
                        ],
                    ],
                ],
                'automation'   => (new ChatAutomationResource($chatAutomation))->resolve(),
                'shops'        => CreateChatAutomation::make()->shops($organisation),
                'triggerTypes' => CreateChatAutomation::make()->triggerTypes(),
                'submitRoute'  => [
                    'name'       => 'grp.models.org.chat.automation.update',
                    'parameters' => ['organisation' => $organisation->id, 'chatAutomation' => $chatAutomation->id],
                    'method'     => 'patch',
                ],
            ]
        );
    }
}
