<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\UI\Marketing\MarketingHub;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Enums\UI\Mail\MailshotTabsEnum;
use App\Models\Web\Website;

/**
 * @property Mailshot $mailshot
 */
class ShowMailshot extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Mailshot $mailshot): Mailshot
    {
        return $mailshot;
    }

    public function inOrganisation(Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisation($mailshot->organisation, $request)->withTab(MailshotTabsEnum::values());

        return $this->handle($mailshot);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($shop, $request)->withTab(MailshotTabsEnum::values());

        return $this->handle($mailshot);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOutboxInWebsite(Organisation $organisation, Shop $shop, Website $website, Outbox $outbox, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($shop, $request)->withTab(MailshotTabsEnum::values());

        return $this->handle($mailshot);
    }


    public function htmlResponse(Mailshot $mailshot, ActionRequest $request): Response
    {
        return Inertia::render(
            'Comms/Mailshot',
            [
                'title'                           => $mailshot->id,
                'breadcrumbs'                     => $this->getBreadcrumbs(
                    $mailshot,
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'pageHead'                        => [
                    'icon'    => 'fal fa-coins',
                    'title'   => 'Mailshot '.$mailshot->id,
                    'edit'    => $this->canEdit ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters())
                        ]
                    ] : false,
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('workshop'),
                            'icon'  => ["fal", "fa-drafting-compass"],
                            'route' => [
                                'name'       => "grp.org.shops.show.marketing.mailshots.workshop",
                                'parameters' => [
                                    $this->organisation->slug,
                                    $this->shop->slug,
                                    $mailshot->slug
                                ]
                            ]
                        ] : [],
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('edit'),
                            'icon'  => ["fal", "fa-sliders-h"],
                            'route' => [
                                'name'       => "grp.org.shops.show.marketing.mailshots.edit",
                                'parameters' => [
                                    $this->organisation->slug,
                                    $this->shop->slug,
                                    $mailshot->slug
                                ]
                            ]
                        ] : []
                    ]
                ],
                'tabs'                            => [
                    'current'    => $this->tab,
                    'navigation' => MailshotTabsEnum::navigation()
                ],
                MailshotTabsEnum::SHOWCASE->value => $this->tab == MailshotTabsEnum::SHOWCASE->value ?
                    fn () => GetMailshotShowcase::run($mailshot)
                    : Inertia::lazy(fn () => GetMailshotShowcase::run($mailshot))
            ]
        );
    }

    public function getBreadcrumbs(Mailshot $mailshot, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Mailshot $mailshot, array $routeParameters, string $suffix) {

            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => $mailshot->type == MailshotTypeEnum::NEWSLETTER ? __('Newsletters') : __('Mailshots')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $mailshot->subject,
                        ],

                    ],
                    'suffix'         => $suffix
                ],
            ];
        };


        return match ($routeName) {
            'grp.org.shops.show.marketing.mailshots.show' => array_merge(
                MarketingHub::make()->getBreadcrumbs(
                    'grp.marketing.shops.show.hub',
                    Arr::only($routeParameters, ['organisation', 'shop']),
                ),
                $headCrumb(
                    $mailshot,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.marketing.mailshots.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.marketing.mailshots.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'mailshot'])
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.marketing.newsletters.show' => array_merge(
                MarketingHub::make()->getBreadcrumbs(
                    'grp.marketing.shops.show.hub',
                    Arr::only($routeParameters, ['organisation', 'shop']),
                ),
                $headCrumb(
                    $mailshot,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.marketing.newsletters.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.marketing.newsletters.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'mailshot'])
                        ]
                    ],
                    $suffix
                )
            ),


            default => []
        };
    }


}
