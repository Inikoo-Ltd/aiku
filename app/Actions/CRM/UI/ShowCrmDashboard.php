<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Nov 2025 09:30:15 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\UI;

use App\Actions\CRM\Customer\UI\GetCustomersDashboard;
use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Enums\UI\CRM\CrmDashboardTabsEnum;
use App\Enums\UI\CRM\CustomersTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
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

        return Inertia::render(
            'Org/CRM/CrmDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-user-hard-hat'],
                        'title' => $title
                    ],
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'title' => $title
                    ],
                    'title'     => $title,
                ],
                'tabs'                              => [
                    'current'    => $this->tab,
                    'navigation' => CrmDashboardTabsEnum::navigation()
                ],

                CrmDashboardTabsEnum::CUSTOMERS->value => $this->tab == CrmDashboardTabsEnum::CUSTOMERS->value ?
                    fn () => GetCustomersDashboard::run($this->shop, $request)
                    : Inertia::lazy(fn () => GetCustomersDashboard::run($this->shop, $request)),


            ]
        );
    }

    public function getBreadcrumbs($routeParameters): array
    {
        return [];
    }
}
