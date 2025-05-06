<?php
/*
 * author Arya Permana - Kirin
 * created on 06-05-2025-15h-23m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\UI\Topup;

use App\Actions\OrgAction;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Purge\PurgeTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateRetinaTopUp extends RetinaAction
{
    public function handle(ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                ),
                'title'    => __('new topup'),
                'pageHead' => [
                    'title'        => __('new topup'),
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => 'retina.topup.index',
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'fullLayout' => true,
                    'blueprint'  =>
                        [
                            [
                                'title'  => __('New TopUp'),
                                'fields' => [
                                ]
                            ]
                        ],
                    'route' => [
                        // 'name'       => '',
                        // 'parameters' => [
                        // ]
                    ]
                ],

            ]
        );
    }

    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($request);
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            IndexRetinaTopUp::make()->getBreadcrumbs(
            ),
            [
                [
                    'type'         => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating topup'),
                    ]
                ]
            ]
        );
    }
}
