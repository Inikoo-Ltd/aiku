<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Jul 2026 22:41:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reports\UI;

use App\Actions\OrgAction;
use App\Actions\Reports\GetEprPackagingCompleteness;
use App\Actions\Reports\GetEprShipmentPackaging;
use App\Actions\Reports\GetEuPackagingReturn;
use App\Actions\Reports\GetUkPackagingReturn;
use App\Actions\UI\Reports\IndexReports;
use App\Enums\Goods\Packaging\PackagingMaterialCategoryEnum;
use App\Enums\UI\Reports\PackagingReportTabsEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class IndexPackagingReport extends OrgAction
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('org-reports.'.$this->organisation->id);
    }

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request)->withTab(array_keys($this->navigation($organisation)));

        return $organisation;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function navigation(Organisation $organisation): array
    {
        $excluded = [];
        if ($organisation->country?->code !== 'GB') {
            $excluded[] = PackagingReportTabsEnum::UK_RETURN;
        }
        if (!GetEuPackagingReturn::make()->scheme($organisation)) {
            $excluded[] = PackagingReportTabsEnum::EU_RETURN;
        }

        return PackagingReportTabsEnum::navigationExcept($excluded);
    }

    /**
     * The period asked for, or the last complete half-year: UK packaging data is collected by half-year. The EU scheme
     * return defaults to the last complete quarter, as Slovakia reports quarterly.
     *
     * @return array{Carbon, Carbon}
     */
    public function period(ActionRequest $request): array
    {
        if ($request->filled(['from', 'to'])) {
            return [Carbon::parse($request->input('from'))->startOfDay(), Carbon::parse($request->input('to'))->startOfDay()];
        }

        if ($request->input('tab') === PackagingReportTabsEnum::EU_RETURN->value) {
            $from = now()->firstOfQuarter()->subQuarterNoOverflow();

            return [$from, $from->copy()->lastOfQuarter()->startOfDay()];
        }

        $from = now()->month > 6 ? now()->startOfYear() : now()->subYear()->month(7)->startOfMonth();

        return [$from, $from->copy()->addMonths(6)->subDay()];
    }

    public function htmlResponse(Organisation $organisation, ActionRequest $request): Response
    {
        [$from, $to] = $this->period($request);

        return Inertia::render(
            'Org/Reports/PackagingReport',
            [
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $this->navigation($organisation),
                ],
                'ownBrandImports' => $request->boolean('own_brand_imports'),
                'ukReturnRoute'   => [
                    'name'       => 'grp.org.reports.packaging.uk-return',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                PackagingReportTabsEnum::COMPLETENESS->value => $this->tab == PackagingReportTabsEnum::COMPLETENESS->value
                    ? fn () => GetEprPackagingCompleteness::run($organisation, $from, $to)
                    : Inertia::optional(fn () => GetEprPackagingCompleteness::run($organisation, $from, $to)),
                PackagingReportTabsEnum::EU_RETURN->value => $this->tab == PackagingReportTabsEnum::EU_RETURN->value
                    ? fn () => GetEuPackagingReturn::run($organisation, $from, $to)
                    : Inertia::optional(fn () => GetEuPackagingReturn::run($organisation, $from, $to)),
                'euReturnRoute' => [
                    'name'       => 'grp.org.reports.packaging.eu-return',
                    'parameters' => $request->route()->originalParameters(),
                ],
                PackagingReportTabsEnum::SHIPMENT->value => $this->tab == PackagingReportTabsEnum::SHIPMENT->value
                    ? fn () => GetEprShipmentPackaging::run($organisation, $from, $to)
                    : Inertia::optional(fn () => GetEprShipmentPackaging::run($organisation, $from, $to)),
                'organisationId' => $organisation->id,
                'materials'      => PackagingMaterialCategoryEnum::labels(),
                PackagingReportTabsEnum::UK_RETURN->value => $this->tab == PackagingReportTabsEnum::UK_RETURN->value
                    ? fn () => GetUkPackagingReturn::run($organisation, $from, $to, $request->boolean('own_brand_imports'))
                    : Inertia::optional(fn () => GetUkPackagingReturn::run($organisation, $from, $to, $request->boolean('own_brand_imports'))),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title' => __('Packaging EPR'),
                'pageHead' => [
                    'icon' => [
                        'icon' => ['fal', 'fa-boxes'],
                        'title' => __('Packaging EPR')
                    ],
                    'title' => __('Packaging EPR'),
                ],
                'downloadRoute' => [
                    'name' => 'grp.org.reports.packaging.download',
                    'parameters' => $request->route()->originalParameters()
                ],
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexReports::make()->getBreadcrumbs($routeName, $routeParameters),
            [
                [
                    'type' => 'simple',
                    'simple' => [
                        'icon' => 'fal fa-boxes',
                        'label' => __('Packaging EPR'),
                        'route' => [
                            'name' => 'grp.org.reports.packaging',
                            'parameters' => $routeParameters
                        ]
                    ]
                ]
            ]
        );
    }
}
