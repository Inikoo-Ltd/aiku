<?php

namespace App\Actions\Discounts\Offer\UI;

use App\Actions\Discounts\Offer\GetOfferCalendarData;
use App\Actions\Helpers\Shop\UI\GetShopOptions;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Http\Resources\Catalogue\OfferCalendarRangeResource;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;

class ShowOfferCalendar
{
    use AsAction;

    public function handle(ActionRequest $request): Response
    {
        $year = (int) $request->input('year', now()->year);
        $month = $request->has('month') ? (int) $request->input('month') : null;
        if ($month !== null && ($month < 1 || $month > 12)) {
            $month = null;
        }
        $limit = $request->has('limit') ? (int) $request->input('limit') : 50;
        if ($limit !== null && $limit < 1) {
            $limit = null;
        }

        $campaignTypeValue = $request->input('campaign_type');
        $campaignTypeEnum = $campaignTypeValue ? OfferCampaignTypeEnum::tryFrom($campaignTypeValue) : null;
        $shopId = $request->has('shop') ? (int) $request->input('shop') : null;

        $organisationSlug = $request->query('organisation');
        $routeOrganisation = $request->route('organisation');

        $organisation = null;

        if ($routeOrganisation instanceof Organisation) {
            $organisation = $routeOrganisation;
        } elseif (is_string($routeOrganisation) && $routeOrganisation !== '') {
            $organisation = Organisation::query()
                ->where('slug', $routeOrganisation)
                ->where('group_id', group()->id)
                ->first();
        }

        if (!$organisation) {
            $organisation = Organisation::query()
                ->where('group_id', group()->id)
                ->when($organisationSlug, fn ($q) => $q->where('slug', $organisationSlug))
                ->firstOrFail();
        }

        $shopOptions = GetShopOptions::run($organisation->slug);
        $selectedShop = collect($shopOptions)->first(fn ($item) => (int) ($item['value'] ?? 0) === (int) $shopId);
        $selectedShopId = $selectedShop['value'] ?? null;

        $calendarData = GetOfferCalendarData::run($organisation, $year, $campaignTypeEnum, $month, $limit, $selectedShopId);

        $ranges = OfferCalendarRangeResource::collection(
            collect($calendarData['holidayRanges'] ?? [])
        )->toArray($request);

        $calendar = Arr::except($calendarData, ['holidayRanges']);
        $calendar['holidayRanges'] = $ranges;
        $campaignTypeLegend = $this->getCampaignTypeLegend();
        $calendar['campaignTypeLegend'] = $campaignTypeLegend;
        $calendar['organisationSlug'] = $organisation->slug;
        $calendar['filters'] = [
            'campaign_type' => $campaignTypeEnum?->value,
            'shop'          => $selectedShopId,
            'limit'         => $limit,
            'year'          => $year,
            'month'         => $month,
        ];
        $calendar['filterOptions'] = [
            'campaignTypes' => collect($campaignTypeLegend)
                ->map(fn ($item) => [
                    'value' => $item['type'],
                    'label' => $item['label'],
                ])
                ->values()
                ->all(),
            'shops' => collect($shopOptions)
                ->map(fn ($item) => [
                    'value' => (string) ($item['value'] ?? ''),
                    'label' => $item['label'] ?? '',
                ])
                ->values()
                ->all(),
        ];

        return Inertia::render('Org/Discounts/OfferCalendar', [
            'breadcrumbs' => $this->getBreadcrumbs(
                $organisation,
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'title'       => __('Offer Timeline'),
            'pageHead'    => [
                'icon'  => [
                    'icon'  => ['fal', 'fa-badge-percent'],
                    'title' => __('Offer Timeline'),
                ],
                'title' => __('Offer Timeline'),
            ],
            'calendar'    => $calendar,
        ]);
    }

    protected function getCampaignTypeLegend(): array
    {
        $palette = [
            '#0ea5e9',
            '#10b981',
            '#8b5cf6',
            '#f59e0b',
            '#6366f1',
            '#d946ef',
            '#14b8a6',
            '#f43f5e',
            '#f97316',
            '#84cc16',
            '#06b6d4',
            '#3b82f6',
        ];

        return collect(OfferCampaignTypeEnum::cases())
            ->values()
            ->map(fn (OfferCampaignTypeEnum $type, int $index) => [
                'type'   => $type->value,
                'label'  => $type->labels()[$type->value] ?? $type->value,
                'color'  => $palette[$index % count($palette)],
            ])
            ->values()
            ->all();
    }

    protected function getBreadcrumbs($organisation, string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            default => array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'icon'  => 'fal fa-badge-percent',
                            'route' => [
                                'name'       => 'grp.org.offer.calendar',
                                'parameters' => ['organisation' => $organisation->slug],
                            ],
                            'label' => __('Offer Timeline'),
                        ]
                    ]
                ]
            )
        };
    }
}
