<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jul 2024 23:32:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $model_type
 * @property mixed $result
 * @property mixed $model_id
 */
class UniversalSearchResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'model_type' => $this->model_type,
            'model_id' => $this->model_id,
            'model_icon' => $this->getModelIcon($this->model_type),
            'result' => $this->result,
        ];
    }

    private function getModelIcon($model_type): ?array
    {
        $icons = [
            'Customer' => [
                'icon' => 'fal fa-user',
                // 'color' => 'primary'
            ],
            'order' => [
                'icon' => 'fal fa-shopping-cart',
                // 'color' => 'success'
            ],
            'product' => [
                'icon' => 'fal fa-box',
                // 'color' => 'info'
            ],
            'invoice' => [
                'icon' => 'fal fa-file-invoice',
                // 'color' => 'warning'
            ],
            'payment' => [
                'icon' => 'fal fa-money-bill',
                // 'color' => 'danger'
            ],
            'shipment' => [
                'icon' => 'fal fa-truck',
                // 'color' => 'secondary'
            ],
            'task' => [
                'icon' => 'fal fa-tasks',
                // 'color' => 'dark'
            ],
            'note' => [
                'icon' => 'fal fa-sticky-note',
                // 'color' => 'info'
            ],
            'event' => [
                'icon' => 'fal fa-calendar-alt',
                // 'color' => 'primary'
            ],
            'file' => [
                'icon' => 'fal fa-file',
                // 'color' => 'secondary'
            ],
            'user' => [
                'icon' => 'fal fa-user',
                // 'color' => 'primary'
            ],
            'group' => [
                'icon' => 'fal fa-users',
                // 'color' => 'secondary'
            ],
            'shop' => [
                'icon' => 'fal fa-store',
                // 'color' => 'info'
            ],
            'organisation' => [
                'icon' => 'fal fa-building',
                // 'color' => 'warning'
            ],
            'warehouse' => [
                'icon' => 'fal fa-warehouse',
                // 'color' => 'dark'
            ],
        ];

        return $icons[$model_type] ?? null;

    }
}
