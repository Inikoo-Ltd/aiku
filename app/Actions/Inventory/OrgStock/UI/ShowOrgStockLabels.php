<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\OrgAction;
use App\Actions\Production\Artefact\UI\GetArtefactCompliance;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Enums\UI\Inventory\OrgStockLabelsTabsEnum;
use App\Models\Inventory\OrgStock;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgStockLabels extends OrgAction
{
    use WithInventoryAuthorisation {
        authorize as inventoryAuthorize;
    }
    use WithOrgStock;
    use WithOrgStockNavigation;
    use WithOrgStockSubNavigation;

    private string $tabsEnum = OrgStockLabelsTabsEnum::class;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(GroupPermissionsEnum::COMPLIANCE_VIEW->value) || $this->inventoryAuthorize($request);
    }

    public function handle(OrgStock $orgStock): OrgStock
    {
        return $orgStock;
    }

    public function htmlResponse(OrgStock $orgStock, ActionRequest $request): Response
    {
        $user      = $request->user();
        $abilities = [
            'edit'          => $user->authTo(GroupPermissionsEnum::COMPLIANCE_EDIT->value),
            'publish'       => $user->authTo(GroupPermissionsEnum::COMPLIANCE_PUBLISH->value),
            'set_mandatory' => $user->authTo(GroupPermissionsEnum::COMPLIANCE->value),
        ];
        $labels    = fn () => GetOrgStockLabels::run($orgStock, 'grp.models.org_stock.', ['orgStock' => $orgStock->id], $abilities);

        return Inertia::render(
            'Org/Inventory/OrgStockLabels',
            [
                'title'       => __('SKO').' '.$orgStock->code.' ('.__('Labels').')',
                'breadcrumbs' => $this->getBreadcrumbs(
                    $orgStock,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPreviousModel($orgStock, $request),
                    'next'     => $this->getNextModel($orgStock, $request),
                ],
                'pageHead'    => [
                    'icon'          => [
                        'title' => __('SKO').' ('.__('Labels').')',
                        'icon'  => 'fal fa-box'
                    ],
                    'model'         => __('SKO'),
                    'title'         => $orgStock->code,
                    'subNavigation' => $this->getOrgStockSubNavigation($orgStock, $request),
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => OrgStockLabelsTabsEnum::navigation()
                ],

                OrgStockLabelsTabsEnum::LABELS->value => $this->tab == OrgStockLabelsTabsEnum::LABELS->value
                    ? $labels
                    : Inertia::optional($labels),

                OrgStockLabelsTabsEnum::COMPLIANCE->value => $this->tab == OrgStockLabelsTabsEnum::COMPLIANCE->value
                    ? fn () => GetArtefactCompliance::run($orgStock)
                    : Inertia::optional(fn () => GetArtefactCompliance::run($orgStock)),
            ]
        );
    }

    public function getBreadcrumbs(OrgStock $orgStock, string $routeName, array $routeParameters): array
    {
        $routeName = preg_replace('/\.labels$/', '', $routeName);

        return ShowOrgStock::make()->getBreadcrumbs($orgStock, $routeName, $routeParameters, '('.__('Labels').')');
    }
}
