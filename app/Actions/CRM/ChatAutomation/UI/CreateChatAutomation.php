<?php

namespace App\Actions\CRM\ChatAutomation\UI;

use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\CRM\Livechat\ChatAutomationTriggerEnum;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateChatAutomation extends OrgAction
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
        return Inertia::render(
            'Org/Chat/AutomationForm',
            [
                'breadcrumbs'  => ShowChatAutomations::make()->getBreadcrumbs($request->route()->originalParameters()),
                'title'        => __('New Automated Message'),
                'pageHead'     => [
                    'title'   => __('New Automated Message'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-robot'],
                        'title' => __('New Automated Message'),
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
                'automation'   => null,
                'shops'        => $this->shops($organisation),
                'triggerTypes' => $this->triggerTypes(),
                'submitRoute'  => [
                    'name'       => 'grp.models.org.chat.automation.store',
                    'parameters' => ['organisation' => $organisation->id],
                    'method'     => 'post',
                ],
            ]
        );
    }

    public function shops(Organisation $organisation): array
    {
        return $organisation->shops()
            ->where('state', ShopStateEnum::OPEN)
            ->orderBy('name')
            ->get()
            ->map(fn ($shop) => [
                'id'   => $shop->id,
                'name' => $shop->name,
                'code' => $shop->code,
            ])
            ->all();
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
}
