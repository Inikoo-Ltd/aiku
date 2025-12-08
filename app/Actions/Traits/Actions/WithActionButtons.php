<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Oct 2023 20:45:31 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Actions;

use Lorisleiva\Actions\ActionRequest;

trait WithActionButtons
{
    protected function getDeleteActionIcon(ActionRequest $request): array
    {
        return [
            'type' => 'button',
            'tooltip' => __('Delete'),
            'icon' => 'fal fa-trash-alt',
            'style' => 'negative',
            'route' => [
                'name' => preg_replace('/(show|dashboard)$/', 'edit', $request->route()->getName()),
                'parameters' => array_merge(
                    [
                        '_query' => [
                            'section' => 'delete',
                        ],
                    ],
                    $request->route()->originalParameters()
                ),
            ],
        ];
    }

    protected function getEditActionIcon(ActionRequest $request, ?string $sectionName = 'properties'): array
    {
        return [
            'type' => 'button',
            'tooltip' => __('Edit'),
            'icon' => 'fal fa-pencil',
            'label' => __('Edit'),
            'style' => 'edit',
            'route' => [
                'name' => preg_replace('/(show|dashboard)$/', 'edit', $request->route()->getName()),
                'parameters' => array_merge(
                    [
                        '_query' => [
                            'section' => $sectionName,
                        ],
                    ],
                    $request->route()->originalParameters()
                ),
            ],
        ];
    }
}
