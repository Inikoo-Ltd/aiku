<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 01 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Comms\Mailshot\GetShopMailshotsSentStats;
use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalShopsMailshotsResource extends JsonResource
{
    use WithDashboardIntervalValuesFromArray;

    public function toArray($request): array
    {
        $summedData = $this->sumIntervalValuesFromArrays($this->resource, GetShopMailshotsSentStats::FIELDS);

        return [
            'slug'    => 'totals',
            'columns' => array_merge(
                [
                    'label'          => [
                        'formatted_value' => __('All Shops'),
                        'align'           => 'left',
                    ],
                    'label_minified' => [
                        'formatted_value' => __('All'),
                        'tooltip'         => __('All Shops'),
                        'align'           => 'left',
                    ],
                ],
                $this->getDashboardColumnsFromArray($summedData, [
                    'newsletters',
                    'newsletters_minified',
                    'marketing_mailshots',
                    'marketing_mailshots_minified',
                    'abandoned_cart_mailshots',
                    'abandoned_cart_mailshots_minified',
                    'mailshots',
                    'mailshots_minified',
                    'mailshots_delta',
                    'abandoned_cart_reminder_emails',
                    'abandoned_cart_reminder_emails_minified',
                    'emails_sent',
                    'emails_sent_minified',
                    'emails_sent_delta',
                ])
            ),
        ];
    }
}
