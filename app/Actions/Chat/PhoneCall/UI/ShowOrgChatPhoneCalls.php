<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\PhoneCall\UI;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\Chat\WithChatScopeNavigation;
use App\Actions\OrgAction;
use App\Http\Resources\CRM\Livechat\ChatPhoneCallResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgChatPhoneCalls extends OrgAction
{
    use WithChatAgentAuthorisation;
    use WithChatScopeNavigation;

    public function handle(Organisation|Shop $parent): Organisation|Shop
    {
        return $parent;
    }

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function authorize(ActionRequest $request): bool
    {
        $user = $request->user();

        if (isset($this->shop)) {
            return $this->userCanViewChatOnShop($user, $this->shop);
        }

        return $this->userCanWorkChatOnOrganisation($user, $this->organisation)
            || $user->authTo(['org-supervisor.'.$this->organisation->id]);
    }

    public function htmlResponse(Organisation|Shop $parent, ActionRequest $request): Response
    {
        $index = IndexChatPhoneCalls::make();
        $calls = $index->handle($parent, 'phone_calls');

        return Inertia::render(
            'Grp/Chat/PhoneCalls',
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
        )->table($index->tableStructure($parent, 'phone_calls'));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            $this->chatParentBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-phone',
                        'route' => $this->chatRoute('phone_calls.index'),
                        'label' => __('Phone calls'),
                    ],
                ],
            ]
        );
    }
}
