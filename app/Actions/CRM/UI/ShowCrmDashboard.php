<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Nov 2025 09:30:15 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\ChatSession\UI\IndexChatSessions;
use App\Actions\CRM\Customer\UI\GetCustomersDashboard;
use App\Actions\CRM\Customer\UI\IndexCustomerCountries;
use App\Actions\CRM\Prospect\UI\GetProspectsDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Enums\UI\CRM\CrmDashboardTabsEnum;
use App\Http\Resources\CRM\CustomerCountriesResource;
use App\Http\Resources\CRM\Livechat\ChatSessionResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowCrmDashboard extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    public function asController(Organisation $organisation, Shop  $shop, ActionRequest $request): ActionRequest
    {
        $this->initialisationFromShop($shop, $request)->withTab(CrmDashboardTabsEnum::values());

        return $request;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $title = __('CRM Dashboard');

        $inertiaResponse = Inertia::render(
            'Org/Shop/CRM/CRMDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-tachometer-alt'],
                        'title' => $title
                    ],
                    'title'     => $title,
                ],
                'tabs'          => [
                    'current'    => $this->tab,
                    'navigation' => CrmDashboardTabsEnum::navigation()
                ],

                CrmDashboardTabsEnum::CUSTOMERS->value => $this->tab == CrmDashboardTabsEnum::CUSTOMERS->value ?
                    fn () => GetCustomersDashboard::run($this->shop, $request)
                    : Inertia::lazy(fn () => GetCustomersDashboard::run($this->shop, $request)),

                CrmDashboardTabsEnum::PROSPECTS->value => $this->tab == CrmDashboardTabsEnum::PROSPECTS->value ?
                    fn () => GetProspectsDashboard::run($this->shop, $request)
                    : Inertia::lazy(fn () => GetProspectsDashboard::run($this->shop, $request)),

                CrmDashboardTabsEnum::COUNTRIES->value => $this->tab == CrmDashboardTabsEnum::COUNTRIES->value ?
                    fn () => CustomerCountriesResource::collection(IndexCustomerCountries::run($this->shop, CrmDashboardTabsEnum::COUNTRIES->value))
                    : Inertia::lazy(fn () => CustomerCountriesResource::collection(IndexCustomerCountries::run($this->shop, CrmDashboardTabsEnum::COUNTRIES->value))),

                CrmDashboardTabsEnum::CHATS->value => $this->tab == CrmDashboardTabsEnum::CHATS->value ?
                    fn () => ChatSessionResource::collection(IndexChatSessions::run($this->shop, CrmDashboardTabsEnum::CHATS->value))
                    : Inertia::lazy(fn () => ChatSessionResource::collection(IndexChatSessions::run($this->shop, CrmDashboardTabsEnum::CHATS->value))),
            ]
        );

        $inertiaResponse->table(IndexCustomerCountries::make()->tableStructure($this->shop, null, CrmDashboardTabsEnum::COUNTRIES->value));
        $inertiaResponse->table(IndexChatSessions::make()->tableStructure($this->shop, null, CrmDashboardTabsEnum::CHATS->value));

        return $inertiaResponse;
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('CRM Dashboard')
                    ],
                ],
            ]
        );
    }
}
