<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Marketing;

use App\Actions\CRM\TrafficSource\GetAggregatedMarketingOverview;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\UI\Marketing\MarketingPeriodEnum;
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
    private Organisation|Group $parent;

    private MarketingPeriodEnum $period = MarketingPeriodEnum::LAST_30;

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
        $this->initialisation($organisation, $request);
        $this->setPeriod($request);

        return $request;
    }

    public function inGroup(ActionRequest $request): ActionRequest
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);
        $this->setPeriod($request);

        return $request;
    }

    private function setPeriod(ActionRequest $request): void
    {
        $this->period = MarketingPeriodEnum::tryFrom((string) $request->query('period'))
            ?? MarketingPeriodEnum::LAST_30;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $isGroup = $this->parent instanceof Group;
        $title   = $isGroup
            ? __('Marketing').' ('.__('all organisations').')'
            : __('Marketing').' ('.$this->parent->name.')';

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
                'overview'    => array_merge(
                    GetAggregatedMarketingOverview::run($this->parent, $this->period),
                    [
                        'scope'          => $isGroup ? 'group' : 'organisation',
                        'children_label' => $isGroup ? __('Organisations') : __('Shops'),
                        'period_options' => collect(MarketingPeriodEnum::cases())->map(fn ($case) => [
                            'value' => $case->value,
                            'label' => MarketingPeriodEnum::labels()[$case->value],
                        ])->all(),
                    ]
                ),
            ]
        );
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
