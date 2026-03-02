<?php

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

class DashboardHeaderDispatchHubResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'    => 'dispatch',
            'columns' => [
                'label'     => [
                    'formatted_value'   => '',
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'align'             => 'left',
                ],
                'todo'    => [
                    'formatted_value'   => __('To do'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'align'             => 'right',
                    'scope'             => 'todo',
                    'icon'              => ['fal', 'fa-chair']
                ],
//                'queued'    => [
//                    'formatted_value'   => __('Queued'),
//                    'currency_type'     => 'always',
//                    'data_display_type' => 'full',
//                    'align'             => 'right',
//                    'scope'             => 'queued',
//                    'icon'              => ['fal', 'fa-hourglass-start']
//                ],
                'handling' => [
                    'formatted_value'   => __('Picking'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'align'             => 'right',
                    'scope'             => 'handling',
                    'icon'              => ['fal', 'fa-hand-paper']
                ],
                'handling_blocked' => [
                    'formatted_value'   => __('Waiting'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'align'             => 'right',
                    'scope'             => 'handling_blocked',
                    'icon'              => ['fal', 'fa-allergies']
                ],
                'picked' => [
                    'formatted_value'   => __('Picked'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'align'             => 'right',
                    'scope'             => 'picked',
                    'icon'              => ['fal', 'fa-check']
                ],
                'packing' => [
                    'formatted_value'   => __('Packing'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'align'             => 'right',
                    'scope'             => 'packing',
                    'icon'              => ['fal', 'fa-box-open']
                ],
                'packed' => [
                    'formatted_value'   => __('Packed'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'align'             => 'right',
                    'scope'             => 'packed',
                    'icon'              => ['fal', 'fa-box-check']
                ],
                'finalised' => [
                    'formatted_value'   => __('Finalised'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'align'             => 'right',
                    'scope'             => 'finalised',
                    'icon'              => ['fal', 'fa-box-check']
                ],
                'total' => [
                    'formatted_value'   => __('Total'),
                    'currency_type'     => 'always',
                    'data_display_type' => 'full',
                    'align'             => 'right',
                    'scope'             => 'total'
                ],
            ]
        ];
    }
}
