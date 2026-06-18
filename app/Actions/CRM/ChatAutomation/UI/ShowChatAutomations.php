<?php

namespace App\Actions\CRM\ChatAutomation\UI;

use App\Actions\CRM\ChatSession\UI\ShowChatConversations;
use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Enums\CRM\Livechat\ChatAutomationTriggerEnum;
use App\Http\Resources\CRM\Livechat\ChatAutomationResource;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowChatAutomations extends OrgAction
{
    use AsAction;
    use WithInertia;

    public function handle(Organisation $organisation): Organisation
    {
        return $organisation;
    }

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(Organisation $organisation, ActionRequest $request): Response
    {
        $automations = IndexChatAutomations::make()->handle($organisation, 'automations');

        return Inertia::render(
            'Org/Chat/Automations',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Automated Messages'),
                'pageHead'    => [
                    'title'   => __('Automated Messages'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-robot'],
                        'title' => __('Automated Messages'),
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('New Automation'),
                            'route' => [
                                'name'       => 'grp.org.chat.automations.create',
                                'parameters' => ['organisation' => $organisation->slug],
                            ],
                        ],
                    ],
                ],
                'triggerTypes' => $this->triggerTypes(),
                'data'         => ChatAutomationResource::collection($automations),
            ]
        )->table(IndexChatAutomations::make()->tableStructure('automations'));
    }

    public function triggerTypes(): array
    {
        $labels       = ChatAutomationTriggerEnum::labels();
        $descriptions = ChatAutomationTriggerEnum::descriptions();

        return collect(ChatAutomationTriggerEnum::cases())
            ->map(fn ($case) => [
                'value'       => $case->value,
                'label'       => $labels[$case->value] ?? $case->value,
                'description' => $descriptions[$case->value] ?? '',
            ])
            ->all();
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowChatConversations::make()->getBreadcrumbs(
                'grp.org.chat.conversations.show',
                ['organisation' => $routeParameters['organisation']]
            ),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-robot',
                        'route' => [
                            'name'       => 'grp.org.chat.automations.show',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Automations'),
                    ],
                ],
            ]
        );
    }
}
