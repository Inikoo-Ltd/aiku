<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\UI;

use App\Actions\Inventory\OrgStock\UI\GetOrgStockLabels;
use App\Actions\OrgAction;
use App\Actions\Production\Artefact\UI\GetArtefactCompliance;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Enums\UI\Inventory\OrgStockLabelsTabsEnum;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The labels and compliance items of a master SKO, one set for every organisation stocking it.
 * Texts that differ by organisation, such as who imports it, are read from the organisation that
 * prints, so the editor previews them for one of the shop organisations stocking it.
 */
class ShowStockLabels extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([GroupPermissionsEnum::COMPLIANCE_VIEW->value, 'goods.view']);
    }

    public function handle(Stock $stock): Stock
    {
        return $stock;
    }

    public function asController(Stock $stock, ActionRequest $request): Stock
    {
        $this->initialisationFromGroup($stock->group, $request)->withTab(OrgStockLabelsTabsEnum::values());

        return $this->handle($stock);
    }

    public function getPreviewOrgStock(Stock $stock): ?OrgStock
    {
        return $stock->orgStocks()
            ->join('organisations', 'organisations.id', 'org_stocks.organisation_id')
            ->orderByRaw('organisations.type = ? desc', [OrganisationTypeEnum::SHOP->value])
            ->orderByRaw('org_stocks.state = ? desc', [OrgStockStateEnum::ACTIVE->value])
            ->orderBy('org_stocks.organisation_id')
            ->select('org_stocks.*')
            ->first();
    }

    public function htmlResponse(Stock $stock, ActionRequest $request): Response
    {
        $user      = $request->user();
        $orgStock  = $this->getPreviewOrgStock($stock);
        $abilities = [
            'edit'          => $user->authTo(GroupPermissionsEnum::COMPLIANCE_EDIT->value),
            'publish'       => $user->authTo(GroupPermissionsEnum::COMPLIANCE_PUBLISH->value),
            'set_mandatory' => $user->authTo(GroupPermissionsEnum::COMPLIANCE->value),
        ];

        $labels     = fn () => $orgStock ? GetOrgStockLabels::run($orgStock, 'grp.models.org_stock.', ['orgStock' => $orgStock->id], $abilities) : null;
        $compliance = fn () => $orgStock ? GetArtefactCompliance::run($orgStock) : null;

        return Inertia::render(
            'Org/Inventory/OrgStockLabels',
            [
                'title'       => __('Master SKO').' '.$stock->code.' ('.__('Labels').')',
                'breadcrumbs' => ShowStock::make()->getBreadcrumbs(
                    $stock,
                    'grp.goods.stocks.show',
                    ['stock' => $stock->slug],
                    '('.__('Labels').')'
                ),
                'pageHead'    => [
                    'icon'  => [
                        'title' => __('Master SKO').' ('.__('Labels').')',
                        'icon'  => 'fal fa-cloud-rainbow'
                    ],
                    'model' => __('Master SKO'),
                    'title' => $stock->code,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => OrgStockLabelsTabsEnum::navigation()
                ],

                OrgStockLabelsTabsEnum::LABELS->value => $this->tab == OrgStockLabelsTabsEnum::LABELS->value
                    ? $labels
                    : Inertia::optional($labels),

                OrgStockLabelsTabsEnum::COMPLIANCE->value => $this->tab == OrgStockLabelsTabsEnum::COMPLIANCE->value
                    ? $compliance
                    : Inertia::optional($compliance),
            ]
        );
    }
}
