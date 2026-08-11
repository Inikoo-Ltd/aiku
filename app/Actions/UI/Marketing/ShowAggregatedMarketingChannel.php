<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 11 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\UI\Marketing;

use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\CRM\TrafficSource\GetAggregatedChannelShowcase;
use App\Actions\CRM\TrafficSource\UI\TrafficSourceTabsEnum;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Http\Resources\CRM\CustomersResource;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * One channel at management level. A channel is a type rather than a record here - every shop keeps
 * its own traffic source row for the same type - so the URL names the type and the page adds those
 * rows up, with a breakdown of the level below.
 */
class ShowAggregatedMarketingChannel extends OrgAction
{
    private Organisation|Group $parent;

    private TrafficSourcesTypeEnum $channelType;

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

    public function asController(Organisation $organisation, string $channelType, ActionRequest $request): ActionRequest
    {
        $this->parent      = $organisation;
        $this->channelType = $this->resolveChannelType($channelType);
        $this->initialisation($organisation, $request)->withTab(TrafficSourceTabsEnum::values());

        return $request;
    }

    public function inGroup(string $channelType, ActionRequest $request): ActionRequest
    {
        $this->parent      = group();
        $this->channelType = $this->resolveChannelType($channelType);
        $this->initialisationFromGroup(group(), $request)->withTab(TrafficSourceTabsEnum::values());

        return $request;
    }

    private function resolveChannelType(string $channelType): TrafficSourcesTypeEnum
    {
        return TrafficSourcesTypeEnum::tryFrom($channelType) ?? abort(404);
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $isGroup = $this->parent instanceof Group;
        $title   = TrafficSourcesTypeEnum::labels()[$this->channelType->value] ?? $this->channelType->value;

        return Inertia::render(
            'Org/Marketing/MarketingChannel',
            [
                'breadcrumbs' => $this->getBreadcrumbs($isGroup, $title),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'model' => $isGroup ? __('Channel').' ('.__('all organisations').')' : __('Channel').' ('.$this->parent->name.')',
                    'icon'  => [
                        'icon'  => ['fal', 'fa-traffic-light'],
                        'title' => __('Channel'),
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => TrafficSourceTabsEnum::navigation(),
                ],
                TrafficSourceTabsEnum::OVERVIEW->value => $this->tab == TrafficSourceTabsEnum::OVERVIEW->value
                    ? fn () => GetAggregatedChannelShowcase::run($this->parent, $this->channelType)
                    : Inertia::optional(fn () => GetAggregatedChannelShowcase::run($this->parent, $this->channelType)),
                TrafficSourceTabsEnum::CUSTOMERS->value => $this->tab == TrafficSourceTabsEnum::CUSTOMERS->value
                    ? fn () => CustomersResource::collection($this->customers())
                    : Inertia::optional(fn () => CustomersResource::collection($this->customers())),
            ]
        )->table(
            IndexCustomers::make()->tableStructure(
                $this->parent,
                [],
                TrafficSourceTabsEnum::CUSTOMERS->value,
                $this->channelType
            )
        );
    }

    private function customers(): LengthAwarePaginator
    {
        return IndexCustomers::make()->handle(
            $this->parent,
            TrafficSourceTabsEnum::CUSTOMERS->value,
            $this->channelType
        );
    }

    public function getBreadcrumbs(bool $isGroup, string $title): array
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
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $isGroup
                            ? ['name' => 'grp.marketing.channels.show', 'parameters' => [$this->channelType->value]]
                            : ['name' => 'grp.org.marketing.channels.show', 'parameters' => [$this->parent->slug, $this->channelType->value]],
                        'label' => $title,
                        'icon'  => 'fal fa-traffic-light',
                    ],
                ],
            ]
        );
    }
}
