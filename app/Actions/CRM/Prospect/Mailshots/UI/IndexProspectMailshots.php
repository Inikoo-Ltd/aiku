<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Oct 2023 15:38:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect\Mailshots\UI;

use App\Actions\CRM\Prospect\UI\IndexProspects;
use App\Actions\InertiaAction;
use App\Actions\Traits\WithProspectsSubNavigation;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\Comms\SenderEmail\SenderEmailStateEnum;
use App\Http\Resources\Mail\MailshotsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProspectMailshots extends InertiaAction
{
    use WithProspectsSubNavigation;

    private Shop $parent;

    protected function getElementGroups(): array
    {
        return [];
    }

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        $this->parent = $shop;

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('contact_name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Mailshot::class)
            ->leftJoin('mailshot_stats', 'mailshot_stats.mailshot_id', 'mailshots.id')
            ->where('type', MailshotTypeEnum::INVITE);

        $queryBuilder->where('parent_id', $shop->id);


        foreach ($this->getElementGroups() as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->defaultSort('mailshots.slug')
            ->allowedSorts(['slug', 'subject', 'date'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups() as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __('No Mailshots'),
                        'description' => $this->canEdit ? __('Get started by creating a new mailshots.') : null,
                        'count'       => 0,
                        'action'      => $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new mailshot'),
                            'label'   => __('mailshot'),
                            'route'   => [
                                'name'       => 'grp.org.shops.show.crm.prospects.mailshots.create',
                                'parameters' => array_values($this->originalParameters)
                            ]
                        ] : null
                    ]
                )
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'subject', label: __('subject'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('date'), sortable: true)
                ->column(key: 'number_recipients', label: __('recipients'))
                ->column(key: 'percentage_bounced', label: __('bounces'))
                ->column(key: 'number_delivered', label: __('delivered'))
                ->column(key: 'percentage_opened', label: __('opened'))
                ->column(key: 'percentage_clicked', label: __('clicked'))
                ->column(key: 'percentage_spam', label: __('spam'))
                ->column(key: 'percentage_unsubscribe', label: __('unsubscribed'))
                ->column(key: 'actions', label: ' ')
                ->defaultSort('slug');
        };
    }



    public function htmlResponse(LengthAwarePaginator $mailshots, ActionRequest $request): Response
    {
        $subNavigation = $this->getSubNavigation($request);
        return Inertia::render(
            'CRM/Prospects/Mailshots',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('prospects mailshots'),
                'pageHead'    => [
                    'title'            => __('prospects mailshots'),
                    'subNavigation'    => $subNavigation,
                    'actions'          =>
                        [
                            ($this->parent->prospects_sender_email_id && $this->parent->prospectsSenderEmail->state == SenderEmailStateEnum::VERIFIED) ? [
                                'type'  => 'button',
                                'style' => 'create',
                                'label' => __('New mailshot'),
                                'route' => [
                                    'name'       => 'grp.org.shops.show.crm.prospects.mailshots.create',
                                    'parameters' => array_values($this->originalParameters)
                                ]
                            ] : null
                        ]


                ],

                'senderEmail' =>
                    $this->parent->prospects_sender_email_id ?
                        SenderEmailResource::make($this->parent->prospectsSenderEmail)->getArray() : null,


                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => ProspectsMailshotsTabsEnum::navigation(),
                ],

                ProspectsMailshotsTabsEnum::SETTINGS->value => $this->tab == ProspectsMailshotsTabsEnum::SETTINGS->value ?
                    fn () => ProspectMailshotSettings::run($this->parent)
                    : Inertia::lazy(fn () => ProspectMailshotSettings::run($this->parent)),

                ProspectsMailshotsTabsEnum::MAILSHOTS->value => $this->tab == ProspectsMailshotsTabsEnum::MAILSHOTS->value ?
                    fn () => MailshotsResource::collection($mailshots)
                    : Inertia::lazy(fn () => MailshotsResource::collection($mailshots)),


            ]
        )->table($this->tableStructure(prefix: ProspectsMailshotsTabsEnum::MAILSHOTS->value));
    }



    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request)->withTab(ProspectsMailshotsTabsEnum::values());

        return $this->handle($shop, prefix: ProspectsMailshotsTabsEnum::MAILSHOTS->value);
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        return match ($routeName) {
            'grp.org.shops.show.crm.prospects.mailshots.index' =>
            array_merge(
                (new IndexProspects())->getBreadcrumbs(
                    'grp.org.shops.show.crm.prospects.index',
                    $routeParameters
                ),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.crm.prospects.mailshots.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Mailshots'),
                            'icon'  => 'fal fa-bars',
                        ],
                        'suffix' => $suffix

                    ]
                ]
            ),
            default => []
        };
    }


}
