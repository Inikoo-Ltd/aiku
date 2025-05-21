<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:08:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\DispatchedEmail\UI;

use App\Actions\Comms\PostRoom\UI\ShowPostRoom;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\UI\Marketing\MarketingHub;
use App\Http\Resources\Mail\DispatchedEmailsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use App\Models\Comms\PostRoom;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexDispatchedEmails extends OrgAction
{
    private Group|Organisation|Shop $parent;

    public function handle(Group|Mailshot|Outbox|PostRoom|Organisation|Shop|Customer $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->orWhereWith('email_addresses.email', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(DispatchedEmail::class);
        $queryBuilder->leftJoin('email_addresses', 'dispatched_emails.email_address_id', '=', 'email_addresses.id');


        switch (class_basename($parent)) {
            case 'Customer':
                $queryBuilder->where('dispatched_emails.recipient_type', class_basename(Customer::class));
                $queryBuilder->where('dispatched_emails.recipient_id', $parent->id);
                break;
            case 'PostRoom':
                $queryBuilder->where('dispatched_emails.post_room_id', $parent->id);
                break;
            case 'Outbox':
                $queryBuilder->where('dispatched_emails.outbox_id', $parent->id);
                break;
            case 'Mailshot':
                $queryBuilder->where('dispatched_emails.mailshot_id', $parent->id);
                break;
            case 'Organisation':
                $queryBuilder->where('dispatched_emails.organisation_id', $parent->id);
                break;
            case 'Group':
                $queryBuilder->where('dispatched_emails.group_id', $parent->id);
                break;
            case 'Shop':
                $queryBuilder->where('dispatched_emails.shop_id', $parent->id);
                break;
            default:
                abort(404);

        }


        return $queryBuilder
            ->defaultSort('-sent_at')
            ->select([
                'dispatched_emails.id',
                'dispatched_emails.state',
                'dispatched_emails.mask_as_spam',
                'dispatched_emails.number_email_tracking_events',
                'email_addresses.email as email_address',
                'dispatched_emails.sent_at as sent_at',
                'dispatched_emails.number_reads',
                'dispatched_emails.number_clicks',
            ])
            ->allowedSorts(['email_address', 'number_email_tracking_events', 'sent_at', 'number_reads', 'mask_as_spam', 'number_clicks'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'state', label: '', canBeHidden: false, type: 'icon');
            $table->column(key: 'email_address', label: __('Email'), canBeHidden: false, sortable: true);

            $table->column(key: 'sent_at', label: __('Sent Date'), canBeHidden: false, sortable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'shop_name', label: __('shop'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'number_email_tracking_events', label: __('events'), canBeHidden: false, sortable: true);
            $table->column(key: 'number_reads', label: __('reads'), canBeHidden: false, sortable: true)
                ->column(key: 'number_clicks', label: __('clicks'), canBeHidden: false, sortable: true);
            $table->defaultSort('-sent_at');
        };
    }


    public function htmlResponse(LengthAwarePaginator $dispatched_emails, ActionRequest $request): Response
    {
        return Inertia::render(
            'Comms/DispatchedEmails',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('dispatched emails'),
                'pageHead'    => [
                    'title' => __('dispatched emails'),
                    'icon'  => ['fal', 'fa-paper-plane'],
                ],
                ...array_merge(
                    ($this->parent instanceof Group)
                        ?
                        ['data' => DispatchedEmailsResource::collection($dispatched_emails)]
                        :
                        ['dispatched_emails' => DispatchedEmailsResource::collection($dispatched_emails)]
                ),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup($this->parent, $request);

        return $this->handle(parent: $this->parent);
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Dispatched Emails'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'mail.dispatched-emails.index' =>
            array_merge(
                (new MarketingHub())->getBreadcrumbs(
                    $routeName,
                    $routeParameters
                ),
                $headCrumb()
            ),
            'mail.post_rooms.show.dispatched-emails.index' =>
            array_merge(
                (new ShowPostRoom())->getBreadcrumbs(
                    $routeName,
                    $routeParameters
                ),
                $headCrumb([])
            ),
            'grp.overview.comms-marketing.dispatched-emails.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
