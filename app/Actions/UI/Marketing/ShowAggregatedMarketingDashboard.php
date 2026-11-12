<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Marketing;

use App\Actions\CRM\TrafficSource\GetAggregatedMarketingOverview;
use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\WithMarketingPeriod;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\Web\StaticWebsite\UI\IndexStaticWebsites;
use App\Enums\UI\Marketing\AggregatedMarketingDashboardTabsEnum;
use App\Http\Resources\Web\StaticWebsitesResource;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The management-level marketing picture: one dashboard for a whole organisation, one for the group,
 * both aggregating every shop underneath. Neither repeats what the shop dashboard already shows -
 * the children table links down instead, and the drill-down continues there.
 */
class ShowAggregatedMarketingDashboard extends OrgAction
{
    use WithMarketingPeriod;

    private Organisation|Group $parent;

    /**
     * Marketing permissions are shop-scoped - `marketing.<shop_id>.view` - and no unscoped
     * `marketing.view` permission exists, so checking for one would deny everybody. A user who may
     * see marketing for any shop underneath may see the roll-up of those shops.
     */
    public function authorize(ActionRequest $request): bool
    {
        $user = $request->user();

        foreach ($this->parent->shops()->pluck('id') as $shopId) {
            if ($user->authTo("marketing.$shopId.view")) {
                return true;
            }
        }

        return false;
    }

    public function asController(Organisation $organisation, ActionRequest $request): ActionRequest
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(AggregatedMarketingDashboardTabsEnum::values());
        $this->setMarketingPeriod($request->user()->settings);

        return $request;
    }

    public function inGroup(ActionRequest $request): ActionRequest
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request)->withTab(AggregatedMarketingDashboardTabsEnum::values());
        $this->setMarketingPeriod($request->user()->settings);

        return $request;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $isGroup      = $this->parent instanceof Group;
        $userSettings = $request->user()->settings;
        $title        = $isGroup
            ? __('Marketing').' ('.__('all organisations').')'
            : __('Marketing').' ('.$this->parent->name.')';

        $overview = fn () => array_merge(
            GetAggregatedMarketingOverview::run($this->parent, $this->periodFrom, $this->periodTo),
            $this->periodLabels(),
            [
                'scope'          => $isGroup ? 'group' : 'organisation',
                'children_label' => $isGroup ? __('Organisations') : __('Shops'),
            ]
        );

        $infoWebsites = fn () => StaticWebsitesResource::collection(
            IndexStaticWebsites::make()->handle($this->group, AggregatedMarketingDashboardTabsEnum::INFO_WEBSITES->value)
        );

        return Inertia::render(
            'Org/Marketing/AggregatedMarketingDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($isGroup),
                'title'       => $title,
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-bullhorn'],
                        'title' => $title,
                    ],
                    'title' => $title,
                ],
                'intervals'   => $this->intervalsProp($userSettings),
                'settings'    => [],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => AggregatedMarketingDashboardTabsEnum::navigation(),
                ],
                /* The roll-up scans every shop's invoices, orders and customers over the period, which
                   is the slow part of the screen. Deferred, so the page head and the period picker
                   arrive immediately and the figures land when they are ready. */
                AggregatedMarketingDashboardTabsEnum::OVERVIEW->value => $this->tab == AggregatedMarketingDashboardTabsEnum::OVERVIEW->value
                    ? Inertia::defer($overview)
                    : Inertia::optional($overview),

                AggregatedMarketingDashboardTabsEnum::INFO_WEBSITES->value => $this->tab == AggregatedMarketingDashboardTabsEnum::INFO_WEBSITES->value
                    ? $infoWebsites
                    : Inertia::optional($infoWebsites),
            ]
        )->table(IndexStaticWebsites::make()->tableStructure(prefix: AggregatedMarketingDashboardTabsEnum::INFO_WEBSITES->value));
    }

    public function getBreadcrumbs(bool $isGroup): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $isGroup
                            ? ['name' => 'grp.marketing.dashboard']
                            : ['name' => 'grp.org.marketing.dashboard', 'parameters' => [$this->parent->slug]],
                        'label' => $isGroup ? __('Marketing').' ('.__('group').')' : __('Marketing'),
                        'icon'  => 'fal fa-bullhorn',
                    ],
                ],
            ]
        );
    }
}
