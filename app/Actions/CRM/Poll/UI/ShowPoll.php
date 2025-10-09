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
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Http\Resources\CRM\PollOptionsResource;
use App\Http\Resources\CRM\PollResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Poll;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPoll extends OrgAction
{
    use WithCustomersSubNavigation;
    use WithCRMAuthorisation;

    private Organisation|Shop $parent;

    public function handle(Poll $poll): Poll
    {
        return $poll;
    }

    public function asController(Organisation $organisation, Shop $shop, Poll $poll, ActionRequest $request): Poll
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(PollsTabsEnum::values());

        return $this->handle($poll);
    }

    public function htmlResponse(Poll $poll, ActionRequest $request): Response
    {
        $actions = [
            [
                'type'  => 'button',
                'style' => 'edit',
                'label' => __('Edit'),
                'route' => [
                    'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                    'parameters' => array_values($request->route()->originalParameters())
                ]
            ]
        ];

        $navigations = PollsTabsEnum::navigation();

        if ($poll->type == PollTypeEnum::OPEN_QUESTION) {
            unset($navigations[PollsTabsEnum::POLL_OPTIONS->value]);
        }

        $renderData = [
            'title'       => __('Poll details'),
            'breadcrumbs' => $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'pageHead'    => [
                'title'   => $poll->name,
                'model'   => __('Poll'),
                'icon'    => [
                    'icon'  => ['fal', 'fa-poll'],
                    'title' => __('Poll')
                ],
                'iconRight' => $poll->in_registration ? [
                    'icon'  => ['fal', 'fa-eye'],
                    'class' => 'text-green-500 animate-pulse',
                    'tooltip' => __('Visible in registration form'),
                ] : [
                    'icon'  => ['fal', 'fa-eye-slash'],
                    'class' => 'text-gray-400',
                    'tooltip' => __('Not visible in registration form'),
                ],
                'actions' => $actions,
            ],
            'tabs'        => [
                'current'    => $this->tab,
                'navigation' => $navigations,
            ],

            'data'        => PollResource::make($poll)->toarray($request),

        ];

        if ($poll->type != PollTypeEnum::OPEN_QUESTION) {
            $renderData[PollsTabsEnum::POLL_OPTIONS->value] = $this->tab == PollsTabsEnum::POLL_OPTIONS->value ?
                fn () => PollOptionsResource::collection(IndexPollOptions::run($poll, PollsTabsEnum::POLL_OPTIONS->value))
                : Inertia::lazy(fn () => PollOptionsResource::collection(IndexPollOptions::run($poll, PollsTabsEnum::POLL_OPTIONS->value)));
        }

        $response = Inertia::render('CRM/Poll', $renderData);

        if ($poll->type != PollTypeEnum::OPEN_QUESTION) {
            $response->table(
                IndexPollOptions::make()->tableStructure($poll, [], PollsTabsEnum::POLL_OPTIONS->value)
            );
        }

        return $response;
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
            'grp.org.shops.show.crm.polls.edit',
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
