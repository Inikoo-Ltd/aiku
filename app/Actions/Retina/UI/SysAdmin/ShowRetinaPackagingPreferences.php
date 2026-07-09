<?php

/*
 * Author: Andi Ferdiawan
 * Created: Thu, 09 Jul 2026 10:30:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Retina\UI\SysAdmin;

use App\Actions\RetinaAction;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaPackagingPreferences extends RetinaAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }

    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($request);
    }

    public function handle(ActionRequest $request): Response
    {
        $title = __('Packaging preferences');

        return Inertia::render(
            'SysAdmin/RetinaPackagingPreferences',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-gift'],
                        'title' => $title
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaSysAdminDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.sysadmin.packaging-preferences.show'
                            ],
                            'label' => __('Packaging preferences'),
                        ]
                    ]
                ]
            );
    }
}
