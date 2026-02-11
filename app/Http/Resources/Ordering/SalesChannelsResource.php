<?php

namespace App\Http\Resources\Ordering;

use Illuminate\Http\Resources\Json\JsonResource;

class SalesChannelsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'code'              => $this->code,
            'type'              => $this->type,
            'is_active'         => $this->getIsActiveIcon(),
            'show_in_dashboard' => $this->getShowInDashboardIcon(),
            'refunds'           => $this->refunds,
            'invoices'          => $this->invoices,
            'sales'             => $this->sales,
        ];
    }

    protected function getIsActiveIcon(): array
    {
        return $this->is_active
            ? [
                'tooltip' => __('Active'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-green-500',
                'color'   => '#22C55E',
            ]
            : [
                'tooltip' => __('Inactive'),
                'icon'    => 'fal fa-times',
                'class'   => 'text-gray-400',
                'color'   => '#9CA3AF',
            ];
    }

    protected function getShowInDashboardIcon(): array
    {
        return $this->show_in_dashboard
            ? [
                'tooltip' => __('Shown in dashboard'),
                'icon'    => 'fal fa-eye',
                'class'   => 'text-green-500',
                'color'   => '#22C55E',
            ]
            : [
                'tooltip' => __('Hidden from dashboard'),
                'icon'    => 'fal fa-eye-slash',
                'class'   => 'text-gray-400',
                'color'   => '#9CA3AF',
            ];
    }
}
