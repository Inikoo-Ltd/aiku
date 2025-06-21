<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Poll\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\Http\Resources\CRM\PollResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Poll;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * @property Poll $poll
 */
class ShowPoll extends OrgAction
{
    use WithCustomersSubNavigation;
    use WithCRMAuthorisation;

    private Organisation|Shop $parent;

    public function handle(Poll $poll): Poll
    {
        return $poll;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, Poll $poll, ActionRequest $request): Poll
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($poll);
    }

    public function htmlResponse(Poll $poll, ActionRequest $request): Response
    {
        $actions = [
            [
                'type'  => 'button',
                'style' => 'edit',
                'label' => __('edit'),
                'route' => [
                    'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                    'parameters' => array_values($request->route()->originalParameters())
                ]
            ]
        ];

        return Inertia::render(
            'CRM/Poll',
            [
                'title'       => __('Poll details'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'   => $poll->name,
                    'model'   => __('Poll'),
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-poll'],
                            'title' => __('poll')
                        ],
                    'actions' => $actions,
                ],
                'data'        => PollResource::make($poll)->toarray($request),
            ]
        );
    }

    public function jsonResponse(Poll $poll): PollResource
    {
        return PollResource::make($poll);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (Poll $poll, array $routeParameters, $suffix = null) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => $poll->name,
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        $poll = Poll::where('slug', $routeParameters['poll'])->first();

        return match ($routeName) {
            'grp.org.shops.show.crm.polls.show' =>
            array_merge(
                IndexPolls::make()->getBreadcrumbs('grp.org.shops.show.crm.polls.show', [
                    'organisation' => $poll->organisation->slug,
                    'shop'         => $poll->shop->slug,
                ]),
                $headCrumb(
                    $poll,
                    [
                        'name'       => 'grp.org.shops.show.crm.polls.show',
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }
}
