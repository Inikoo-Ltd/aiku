<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 21 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\UI\Dropshipping\Marketing;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\TrafficSource\Attribution\GetShopAcquisitionCohorts;
use App\Actions\CRM\TrafficSource\Attribution\GetShopOrderAttribution;
use App\Actions\CRM\TrafficSource\Attribution\GetShopOrderJourney;
use App\Actions\CRM\TrafficSource\GetAttributionStartedAt;
use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\WithMarketingPeriod;
use App\Enums\Helpers\Period\ComparePeriodEnum;
use App\Enums\UI\Marketing\MarketingDashboardV2TabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMarketingDashboardV2 extends OrgAction
{
    use WithMarketingPeriod;

    private ?string $journeyOrder = null;

    private ?ComparePeriodEnum $compareMode = null;

    private ?Carbon $compareFrom = null;

    private ?Carbon $compareTo = null;

    private ?string $customCompareFrom = null;

    private ?string $customCompareTo = null;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("marketing.{$this->shop->id}.view");
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): ActionRequest
    {
        $this->initialisationFromShop($shop, $request)->withTab(MarketingDashboardV2TabsEnum::values());

        $this->setMarketingPeriod($request->user()->settings);

        $this->journeyOrder = $request->query('order');

        $this->setComparePeriod($request);

        return $request;
    }

    private function setComparePeriod(ActionRequest $request): void
    {
        $this->compareMode       = ComparePeriodEnum::tryFrom((string) $request->query('compare'));
        $this->customCompareFrom = $request->query('compare_from');
        $this->customCompareTo   = $request->query('compare_to');

        if (!$this->compareMode || !$this->periodFrom) {
            return;
        }

        [$this->compareFrom, $this->compareTo] = $this->compareMode->range(
            $this->periodFrom,
            $this->periodTo ?? now()->endOfDay(),
            $this->customCompareFrom,
            $this->customCompareTo
        ) ?? [null, null];
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $title = __('Marketing Dashboard');

        return Inertia::render(
            'Org/Marketing/MarketingDashboardV2',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-bullhorn'],
                        'title' => $title,
                    ],
                    'title' => $title,
                    'afterTitle' => [
                        'label' => __('Attribution'),
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => MarketingDashboardV2TabsEnum::navigation(),
                ],
                'intervals'   => $this->intervalsProp($request->user()->settings),
                'settings'    => [],
                'compare'     => $this->compareProp(),

                MarketingDashboardV2TabsEnum::ACQUISITION->value => $this->tab == MarketingDashboardV2TabsEnum::ACQUISITION->value
                    ? fn () => $this->acquisition()
                    : Inertia::optional(fn () => $this->acquisition()),
                MarketingDashboardV2TabsEnum::ORDERS->value => $this->tab == MarketingDashboardV2TabsEnum::ORDERS->value
                    ? fn () => $this->orders()
                    : Inertia::optional(fn () => $this->orders()),
                MarketingDashboardV2TabsEnum::JOURNEY->value => $this->tab == MarketingDashboardV2TabsEnum::JOURNEY->value
                    ? fn () => GetShopOrderJourney::run($this->shop, $this->journeyOrder)
                    : Inertia::optional(fn () => GetShopOrderJourney::run($this->shop, $this->journeyOrder)),
            ]
        );
    }

    /**
     * @return array{available: bool, mode: string|null, options: array<int, array{value: string, label: string}>, from: string|null, to: string|null, custom_from: string|null, custom_to: string|null, tracking_started_at: string|null}
     */
    private function compareProp(): array
    {
        return [
            'available'           => $this->periodFrom !== null,
            'mode'                => $this->compareFrom ? $this->compareMode?->value : null,
            'options'             => collect(ComparePeriodEnum::cases())->map(fn (ComparePeriodEnum $mode) => [
                'value' => $mode->value,
                'label' => $mode->label(),
            ])->all(),
            'from'                => $this->compareFrom?->toDateString(),
            'to'                  => $this->compareTo?->toDateString(),
            'custom_from'         => $this->customCompareFrom,
            'custom_to'           => $this->customCompareTo,
            'tracking_started_at' => GetAttributionStartedAt::run()?->toDateString(),
        ];
    }

    private function acquisition(): array
    {
        return array_merge(
            GetShopAcquisitionCohorts::run($this->shop, $this->periodFrom, $this->periodTo),
            $this->periodLabels(),
            ['previous' => $this->previous(fn (Carbon $from, Carbon $to) => collect(GetShopAcquisitionCohorts::run($this->shop, $from, $to))->except('cohorts')->all())]
        );
    }

    private function orders(): array
    {
        return array_merge(
            GetShopOrderAttribution::run($this->shop, $this->periodFrom, $this->periodTo),
            $this->periodLabels(),
            ['previous' => $this->previous(fn (Carbon $from, Carbon $to) => GetShopOrderAttribution::run($this->shop, $from, $to))]
        );
    }

    /**
     * The same figures over the compared period, with the dates actually covered: touches exist
     * only from the day tracking began, so a period that starts earlier is cut there, and one that
     * ends earlier has nothing to show.
     */
    private function previous(callable $figures): ?array
    {
        if (!$this->compareFrom) {
            return null;
        }

        $startedAt = GetAttributionStartedAt::run();

        return array_merge($figures($this->compareFrom, $this->compareTo), [
            'requested_from'  => $this->compareFrom->toDateString(),
            'requested_to'    => $this->compareTo->toDateString(),
            'label'           => $this->compareMode->label(),
            'before_tracking' => $startedAt !== null && $this->compareTo->lt($startedAt),
            'cut_by_tracking' => $startedAt !== null && $this->compareFrom->lt($startedAt) && $this->compareTo->gte($startedAt),
        ]);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.marketing.dashboard',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Marketing'),
                    ],
                ],
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.marketing.dashboard_v2',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Attribution'),
                    ],
                ],
            ]
        );
    }
}
