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
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\UI\CRM\ProspectsMailshotsTabsEnum;
use App\Http\Resources\Mail\ProspectMailshotsResource;
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


    protected function getElementGroups(): array
    {
        return [];
    }

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('contact_name', $value)
                    ->orWhere('mailshots.subject', 'like', "%{$value}%")
                    ->orWhere('mailshots.name', 'like', "%{$value}%");
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Mailshot::class)
            ->leftJoin('mailshot_stats', 'mailshot_stats.mailshot_id', 'mailshots.id')
            ->where('type', MailshotTypeEnum::INVITE);

        $queryBuilder->where(function ($query) {
            $query->where('mailshots.is_second_wave', false)
                ->orWhereNotIn('mailshots.state', [MailshotStateEnum::READY->value, MailshotStateEnum::IN_PROCESS->value, MailshotStateEnum::SCHEDULED->value]);
        });

        $queryBuilder->where('shop_id', $shop->id);


        foreach ($this->getElementGroups() as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->defaultSort('-mailshots.date')
            ->select([
                'mailshots.state',
                'mailshots.date',
                'mailshots.slug',
                'mailshots.id',
                'mailshots.subject',
                'mailshots.name',
                'mailshot_stats.number_deliveries_success',
                'mailshot_stats.number_try_send_success',
                'mailshot_stats.number_delivered_open_success',
                'mailshot_stats.number_dispatched_emails as dispatched_emails',
                'mailshot_stats.number_dispatched_emails_state_sent as sent',
                'mailshot_stats.number_dispatched_emails_state_delivered as delivered',
                'mailshot_stats.number_dispatched_emails_state_hard_bounce as hard_bounce',
                'mailshot_stats.number_dispatched_emails_state_soft_bounce as soft_bounce',
                'mailshot_stats.number_dispatched_emails_state_opened as opened',
                'mailshot_stats.number_dispatched_emails_state_clicked as clicked',
                'mailshot_stats.number_dispatched_emails_state_spam as spam',
                'mailshot_stats.number_dispatched_emails_state_unsubscribed as unsubscribed',
            ])
            ->allowedSorts(['subject', 'name', 'date', 'number_try_send_success', 'hard_bounce', 'number_deliveries_success', 'soft_bounce', 'dispatched_emails', 'delivered', 'opened', 'clicked', 'spam', 'unsubscribed'])
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
                    ->pageName($prefix . 'Page');
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
                            'tooltip' => __('New mailshot'),
                            'label'   => __('Mailshot'),
                            'route'   => [
                                'name'       => 'grp.org.shops.show.crm.prospects.mailshots.create',
                                'parameters' => array_values($this->originalParameters)
                            ]
                        ] : null
                    ]
                )
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'subject', label: __('subject'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'number_try_send_success', label: '', icon: 'fal fa-paper-plane', tooltip: __('Sent emails'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'hard_bounce', label: '', icon: 'fal fa-skull', tooltip: __('Hard Bounces'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'soft_bounce', label: '', icon: 'fal fa-dungeon', tooltip: __('Soft Bounces'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_deliveries_success', label: '', icon: 'fal fa-inbox-in', tooltip: __('Delivered'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'opened', label: '', icon: 'fal fa-envelope-open', tooltip: __('Opened'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'clicked', label: '', icon: 'fal fa-hand-pointer', tooltip: __('Clicked'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'spam', label: '', icon: 'fal fa-dumpster-fire', tooltip: __('Spam'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'unsubscribed', label: '', icon: 'fal fa-thumbs-down', tooltip: __('Unsubscribed'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('-date');
        };
    }

    public function htmlResponse(LengthAwarePaginator $mailshots, ActionRequest $request): Response
    {

        $shop = $request->route()->parameters()['shop'];
        $subNavigation = $this->getSubNavigation($shop, $request);
        return Inertia::render(
            'Org/Shop/CRM/ProspectMailshots',
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
                        // TODO: Check later
                        // ($shop->prospects_sender_email_id && $shop->prospectsSenderEmail->state == SenderEmailStateEnum::VERIFIED) ?
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('New mailshot'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.crm.prospects.mailshots.create',
                                'parameters' => array_values($this->originalParameters)
                            ]
                        ]
                        // : null
                    ]


                ],

                // TODO: check later
                'senderEmail' => null,
                // $shop->prospects_sender_email_id ?
                //     SenderEmailResource::make($shop->prospectsSenderEmail)->getArray() : null,


                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => ProspectsMailshotsTabsEnum::navigation(),
                ],

                // ProspectsMailshotsTabsEnum::SETTINGS->value => $this->tab == ProspectsMailshotsTabsEnum::SETTINGS->value ?
                //     fn () => ProspectMailshotSettings::run($shop)
                //     : Inertia::lazy(fn () => ProspectMailshotSettings::run($shop)),

                ProspectsMailshotsTabsEnum::MAILSHOTS->value => $this->tab == ProspectsMailshotsTabsEnum::MAILSHOTS->value ?
                    fn () => ProspectMailshotsResource::collection($mailshots)
                    : Inertia::lazy(fn () => ProspectMailshotsResource::collection($mailshots)),


            ]
        )->table($this->tableStructure(prefix: ProspectsMailshotsTabsEnum::MAILSHOTS->value));
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
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
