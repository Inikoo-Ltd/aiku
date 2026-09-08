<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSourceCampaign\UI;

use App\Actions\OrgAction;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowGoogleAdsCampaign extends OrgAction
{
    public function handle(TrafficSourceCampaign $trafficSourceCampaign): TrafficSourceCampaign
    {
        return $trafficSourceCampaign;
    }

    public function asController(Organisation $organisation, Shop $shop, TrafficSourceCampaign $trafficSourceCampaign, ActionRequest $request): TrafficSourceCampaign
    {
        $this->initialisationFromShop($shop, $request);

        if (
            $trafficSourceCampaign->trafficSource->shop_id !== $shop->id
            || $trafficSourceCampaign->trafficSource->type !== TrafficSourcesTypeEnum::GOOGLE_ADS->value
        ) {
            throw new NotFoundHttpException();
        }

        return $this->handle($trafficSourceCampaign);
    }

    public function htmlResponse(TrafficSourceCampaign $trafficSourceCampaign, ActionRequest $request): Response
    {
        $data = $trafficSourceCampaign->data ?? [];

        $costs = DB::table('traffic_source_costs')
            ->where('traffic_source_campaign_id', $trafficSourceCampaign->id)
            ->where('date', '>=', now()->subDays(90)->toDateString())
            ->orderByDesc('date')
            ->pluck('amount', 'date')
            ->map(fn ($amount, $date) => ['date' => $date, 'amount' => (float) $amount])
            ->values();

        return Inertia::render(
            'Org/Shop/CRM/GoogleAdsCampaign',
            [
                'breadcrumbs' => $this->getBreadcrumbs($trafficSourceCampaign, $request->route()->originalParameters()),
                'title'       => $trafficSourceCampaign->name,
                'pageHead'    => [
                    'title' => $trafficSourceCampaign->name,
                    'icon'  => [
                        'icon'  => ['fab', 'fa-google'],
                        'title' => __('Google Ads campaign'),
                    ],
                    'model' => __('Google Ads campaign'),
                ],
                'campaign' => [
                    'reference'     => $trafficSourceCampaign->reference,
                    'name'          => $trafficSourceCampaign->name,
                    'status'        => $data['status'] ?? null,
                    'channel_type'  => $data['channel_type'] ?? null,
                    'budget_amount' => $data['budget_amount'] ?? null,
                    'currency'      => $data['currency'] ?? $this->shop->currency->code,
                    'fetched_at'    => $data['fetched_at'] ?? null,
                    'spend_30d'     => (float) DB::table('traffic_source_costs')
                        ->where('traffic_source_campaign_id', $trafficSourceCampaign->id)
                        ->where('date', '>=', now()->subDays(30)->toDateString())
                        ->sum('amount'),
                    'spend_total' => (float) DB::table('traffic_source_costs')
                        ->where('traffic_source_campaign_id', $trafficSourceCampaign->id)
                        ->sum('amount'),
                    'shop_currency' => $this->shop->currency->code,
                ],
                'ad_groups'     => $data['ad_groups'] ?? [],
                'metrics_30d'   => $data['metrics_30d'] ?? null,
                'metrics_daily' => $data['metrics_daily'] ?? [],
                'attribution'   => $this->attribution($trafficSourceCampaign),
                'costs'         => $costs,
            ]
        );
    }

    private function attribution(TrafficSourceCampaign $trafficSourceCampaign): array
    {
        $stats   = $trafficSourceCampaign->stats;
        $revenue = (float) ($stats->total_customer_revenue ?? 0);
        $cost    = (float) ($stats->total_cost ?? 0);

        return [
            'customers' => (int) ($stats->number_customers ?? 0),
            'purchases' => (int) ($stats->number_customer_purchases ?? 0),
            'revenue'   => $revenue,
            'cost'      => $cost,
            'roas'      => $cost > 0 ? $revenue / $cost : null,
        ];
    }

    public function getBreadcrumbs(TrafficSourceCampaign $trafficSourceCampaign, array $routeParameters): array
    {
        return array_merge(
            IndexGoogleAdsCampaigns::make()->getBreadcrumbs([
                'organisation' => $routeParameters['organisation'],
                'shop'         => $routeParameters['shop'],
            ]),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.marketing.google_ads.show',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $trafficSourceCampaign->name,
                        'icon'  => 'fab fa-google',
                    ],
                ],
            ],
        );
    }
}
