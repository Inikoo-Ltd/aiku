<?php

/*
 * author Arya Permana - Kirin
 * created on 04-12-2024-15h-10m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Http\Resources\Helpers\SnapshotResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMailshotWorkshop extends OrgAction
{
    use WithActionButtons;

    public function handle(Mailshot $mailshot): Mailshot
    {
        return $mailshot;
    }


    public function asController(Organisation $organisation, Shop $shop, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot);
    }

    public function htmlResponse(Mailshot $mailshot, ActionRequest $request): Response
    {

        $snapshot = $mailshot->email->unpublishedSnapshot;

        $beeFreeSettings = $snapshot->group->settings['beefree'];
        return Inertia::render(
            'Org/Web/Workshop/Outbox/OutboxWorkshop', //NEED VUE FILE
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $mailshot,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $mailshot->subject,
                'pageHead'    => [
                    'title'     => $mailshot->subject,
                    'icon'      => [
                        'tooltip' => __('snapshot'),
                        'icon'    => 'fal fa-mail-bulk'
                    ],
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exit',
                            'label' => __('Exit workshop'),
                            'route' => [
                                'name'       => preg_replace('/workshop$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ]
                        ],
                    ]

                ],
                'snapshot'    => SnapshotResource::make($snapshot)->getArray(),
                'builder'     => $snapshot->builder,
                'imagesUploadRoute'   => [
                    'name'       => 'grp.models.email-templates.images.store',
                    'parameters' => $snapshot->id
                ],
                'updateRoute'         => [
                    'name'       => 'grp.models.shop.outboxes.workshop.update',
                    'parameters' => [
                        'shop' => $mailshot->shop_id,
                        'outbox' => $mailshot->outbox_id
                    ],
                    'method' => 'patch'
                ],
                'loadRoute'           => [
                    'name'       => 'grp.models.email-templates.content.show',
                    'parameters' => $snapshot->id
                ],
                'publishRoute'           => [
                    'name'       => 'grp.models.shop.outboxes.publish',
                    'parameters' => [
                        'shop' => $mailshot->shop_id,
                        'outbox' => $mailshot->outbox_id
                    ],
                    'method' => 'post'
                ],
                'apiKey'            =>  $beeFreeSettings
            ]
        );
    }

    public function getBreadcrumbs(Mailshot $mailshot, string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (string $type, Mailshot $mailshot, array $routeParameters, string $suffix = null) {
            return [
                [
                    'type'           => $type,
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('mailshots')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $mailshot->subject,
                        ],

                    ],
                    'simple'         => [
                        'route' => $routeParameters['model'],
                        'label' => $mailshot->subject
                    ],
                    'suffix'         => $suffix
                ],
            ];
        };


        return match ($routeName) {
            'grp.org.shops.show.marketing.mailshots.workshop' =>
            array_merge(
                ShowMailshot::make()->getBreadcrumbs(
                    $mailshot,
                    'grp.org.shops.show.marketing.mailshots.show',
                    $routeParameters,
                ),
                $headCrumb(
                    'modelWithIndex',
                   $mailshot,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.marketing.mailshots.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.marketing.mailshots.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }



}
