<?php

/*
 * author Arya Permana - Kirin
 * created on 16-12-2024-14h-56m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Comms\EmailBulkRun\UI;

use App\Actions\Comms\Outbox\UI\ShowOutbox;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Mail\EmailBulkRunResource;
use App\Models\Comms\EmailBulkRun;

class ShowEmailBulkRun extends OrgAction
{
    public function handle(EmailBulkRun $emailBulkRun): EmailBulkRun
    {
        return $emailBulkRun;
    }

    public function inOutbox(Organisation $organisation, Shop $shop, Outbox $outbox, EmailBulkRun $emailBulkRun, ActionRequest $request): EmailBulkRun
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($emailBulkRun);
    }

    public function htmlResponse(EmailBulkRun $emailBulkRun, ActionRequest $request): Response
    {
        // dd($collection->stats);
        return Inertia::render(
            'Comms/EmailBulkRun',
            [
                'title'       => __('email bulk run'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($emailBulkRun, $request),
                    'next'     => $this->getNext($emailBulkRun, $request),
                ],
                'pageHead'    => [
                    'title'     => $emailBulkRun->subject,
                    'model'     => __('email bulk run'),
                    'icon'      =>
                        [
                            'icon'  => ['fal', 'fa-cube'],
                            'title' => __('Email bulk run')
                        ],
                    'actions' => []
                    // 'subNavigation' => $this->getCollectionSubNavigation($collection),
                ],
                // 'tabs' => [
                //     'current'    => $this->tab,
                //     'navigation' => CollectionTabsEnum::navigation($collection)
                // ],
            ]
        );
    }

    public function jsonResponse(EmailBulkRun $emailBulkRun): EmailBulkRunResource
    {
        return new EmailBulkRunResource($emailBulkRun);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (EmailBulkRun $emailBulkRun, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Email Bulk Run')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $emailBulkRun->subject,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        $emailBulkRun = EmailBulkRun::where('id', $routeParameters['emailBulkRun'])->first();

        return match ($routeName) {
            'grp.org.shops.show.dashboard.comms.outboxes.show' =>
            array_merge(
                ShowOutbox::make()->getBreadcrumbs($routeName, $routeParameters),
                $headCrumb(
                    $emailBulkRun,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.dashboard.comms.outboxes.show',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.dashboard.comms.outboxes.show.email-bulk-runs.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(EmailBulkRun $emailBulkRun, ActionRequest $request): ?array
    {
        $previous = EmailBulkRun::where('id', '<', $emailBulkRun->id)->orderBy('id', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());

    }

    public function getNext(EmailBulkRun $emailBulkRun, ActionRequest $request): ?array
    {
        $next = EmailBulkRun::where('id', '>', $emailBulkRun->id)->orderBy('id')->first();
        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?EmailBulkRun $emailBulkRun, string $routeName): ?array
    {
        if (!$emailBulkRun) {
            return null;
        }

        return match ($routeName) {
            'grp.org.shops.show.dashboard.comms.outboxes.show.email-bulk-runs.show' => [
                'label' => $emailBulkRun->subject,
                'route' => [
                    'name'      => $routeName,
                    'parameters' => [
                        'organisation'      => $this->organisation->slug,
                        'shop'              => $this->shop->slug,
                        'outbox'            => $emailBulkRun->outbox->slug,
                        'emailBulkRun'      => $emailBulkRun->id
                    ]

                ]
            ],
        };
    }
}
