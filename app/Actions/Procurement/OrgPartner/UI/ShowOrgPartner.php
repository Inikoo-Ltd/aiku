<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 May 2024 12:06:23 British Summer Time, Plane Manchester-Malaga
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\WithOrgPartnerSubNavigation;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\UI\Procurement\OrgPartnerTabsEnum;
use App\Http\Resources\SupplyChain\SupplierResource;
use App\Models\Procurement\OrgPartner;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgPartner extends OrgAction
{
    use WithOrgPartnerSubNavigation;

    public function handle(OrgPartner $orgPartner): OrgPartner
    {
        return $orgPartner;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): OrgPartner
    {
        $this->initialisation($organisation, $request)->withTab(OrgPartnerTabsEnum::values());

        return $this->handle($orgPartner);
    }

    public function htmlResponse(OrgPartner $orgPartner, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Procurement/Partner',
            [
                'title' => __('partner'),
                'breadcrumbs' => $this->getBreadcrumbs($orgPartner, $request->route()->originalParameters()),
                'pageHead' => [
                    'icon' => [
                        'icon' => ['fal', 'fa-users-class'],
                        'title' => __('Partner'),
                    ],
                    'title' => $orgPartner->partner->name,
                    'subNavigation' => $this->getOrgPartnerNavigation($orgPartner),
                ],
                'tabs' => [
                    'current' => $this->tab,
                    'navigation' => OrgPartnerTabsEnum::navigation(),
                ],

                OrgPartnerTabsEnum::SHOWCASE->value => $this->tab == OrgPartnerTabsEnum::SHOWCASE->value ?
                fn () => GetOrgPartnerShowcase::run($orgPartner)
                : Inertia::lazy(fn () => GetOrgPartnerShowcase::run($orgPartner)),

            ]
        );
    }

    public function jsonResponse(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier);
    }

    public function getBreadcrumbs(OrgPartner $orgPartner, array $routeParameters, $suffix = null): array
    {
        return array_merge(
            (new ShowProcurementDashboard)->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
            [
                [
                    'type' => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name' => 'grp.org.procurement.org_partners.index',
                                'parameters' => Arr::only($routeParameters, 'organisation'),

                            ],
                            'label' => __('Partners'),
                        ],
                        'model' => [
                            'route' => [
                                'name' => 'grp.org.procurement.org_partners.show',
                                'parameters' => $routeParameters,
                            ],
                            'label' => $orgPartner->partner->code,
                        ],
                    ],
                    'suffix' => $suffix,

                ],
            ]
        );
    }
}
