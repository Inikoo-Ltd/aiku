<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 Sep 2023 19:02:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web;

trait HasWorkshopAction
{
    protected function workshopActions($request): array
    {
        return
            [
                $this->canEdit ? [
                    'type'    => 'button',
                    'style'   => 'edit',
                    'tooltip' => __('settings'),
                    'icon'    => ["fal", "fa-sliders-h"],
                    'route'   => [
                        'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                        'parameters' => array_values($request->route()->originalParameters())
                    ]
                ] : [],
                $this->canEdit ? [
                    'type'  => 'button',
                    'style' => 'secondary',
                    'label' => __('workshop'),
                    'icon'  => ["fal", "fa-drafting-compass"],
                    'route' => [
                        'name'       => preg_replace('/show$/', 'workshop', $request->route()->getName()),
                        'parameters' => array_values($request->route()->originalParameters())
                    ]
                ] : [],
            ];
    }
}
