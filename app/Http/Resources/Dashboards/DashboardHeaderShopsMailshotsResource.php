<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 01 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardHeaderShopsMailshotsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Organisation|Group $model */
        $model = $this->resource;

        $columns = [
            'label'          => [
                'formatted_value'   => __('Shop'),
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'align'             => 'left',
            ],
            'label_minified' => [
                'formatted_value'   => __('Shop'),
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'align'             => 'left',
            ],
        ];

        $columns = array_merge(
            $columns,
            $this->countColumns('newsletters', __('Newsletters'), __('Newsletter mailshots sent')),
            $this->countColumns('marketing_mailshots', __('Marketing emails'), __('Marketing mailshots sent')),
            $this->countColumns('abandoned_cart_mailshots', __('Abandoned carts'), __('Abandoned cart mailshots sent')),
            $this->countColumns('mailshots', __('Total mailshots'), __('Newsletter, marketing and abandoned cart mailshots sent'), true),
            $this->countColumns('abandoned_cart_reminder_emails', __('Abandoned cart reminders'), __('Emails sent by the automated abandoned cart and abandoned checkout reminders')),
            $this->countColumns('emails_sent', __('Total emails'), __('Emails sent to customers by these mailshots and the abandoned cart reminders, one per recipient'), true),
        );

        return [
            'slug'    => $model->slug,
            'columns' => $columns,
        ];
    }

    private function countColumns(string $field, string $label, string $tooltip, bool $withDelta = false): array
    {
        $columns = [
            $field              => [
                'formatted_value'   => $label,
                'tooltip'           => $tooltip,
                'currency_type'     => 'always',
                'data_display_type' => 'full',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => $field,
            ],
            $field.'_minified' => [
                'formatted_value'   => $label,
                'tooltip'           => $tooltip,
                'currency_type'     => 'always',
                'data_display_type' => 'minified',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => $field,
            ],
        ];

        if ($withDelta) {
            $columns[$field.'_delta'] = [
                'formatted_value'   => 'Δ 1Y',
                'tooltip'           => __('Change versus 1 Year ago'),
                'currency_type'     => 'always',
                'data_display_type' => 'always',
                'sortable'          => true,
                'align'             => 'right',
                'scope'             => $field,
            ];
        }

        return $columns;
    }
}
