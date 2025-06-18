<?php

/*
 * author Arya Permana - Kirin
 * created on 30-12-2024-13h-39m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\OrgAction;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Http\Resources\Mail\InviteMailshotsResource;
use App\Http\Resources\Mail\MailshotResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use App\Models\Comms\PostRoom;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexInviteMailshots extends OrgAction
{
    use HasUIMailshots;

    public Group|Outbox|PostRoom|Organisation|Shop $parent;

    public function handle(Group|Outbox|PostRoom|Organisation|Shop $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('mailshots.subject', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Mailshot::class);
        $queryBuilder->leftJoin('organisations', 'mailshots.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'mailshots.shop_id', '=', 'shops.id');
        $queryBuilder->leftJoin('outboxes', 'mailshots.outbox_id', 'outboxes.id')
            ->leftJoin('mailshot_stats', 'mailshot_stats.mailshot_id', 'mailshots.id')
            ->leftJoin('post_rooms', 'outboxes.post_room_id', 'post_rooms.id')
            ->when($parent, function ($query) use ($parent) {
                if (class_basename($parent) == 'Comms') {
                    $query->where('mailshots.post_room_id', $parent->id);
                }
            });
        $queryBuilder->where('mailshots.type', OutboxCodeEnum::INVITE->value);
        if ($parent instanceof Group) {
            $queryBuilder->where('mailshots.group_id', $parent->id);
        }

        return $queryBuilder
            ->defaultSort('mailshots.id')
            ->select([
                'mailshots.state',
                'mailshots.date',
                'mailshots.slug',
                'mailshots.id',
                'mailshots.subject',
                'outboxes.slug as outboxes_slug',
                'post_rooms.id as post_room_id',
                'mailshot_stats.number_dispatched_emails as dispatched_emails',
                'mailshot_stats.number_dispatched_emails_state_sent as sent',
                'mailshot_stats.number_dispatched_emails_state_delivered as delivered',
                'mailshot_stats.number_dispatched_emails_state_hard_bounce as hard_bounce',
                'mailshot_stats.number_dispatched_emails_state_soft_bounce as soft_bounce',
                'mailshot_stats.number_dispatched_emails_state_opened as opened',
                'mailshot_stats.number_dispatched_emails_state_clicked as clicked',
                'mailshot_stats.number_dispatched_emails_state_spam as spam',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',

            ])
            ->allowedSorts(['state', 'date'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->column(key: 'subject', label: __('subject'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'state', label: __('state'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'shop_name', label: __('shop'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'sent', label: __('sent'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'hard_bounce', label: __('hard bounce'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'soft_bounce', label: __('soft bounce'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'delivered', label: __('delivered'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'opened', label: __('opened'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'clicked', label: __('clicked'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'spam', label: __('spam'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $mailshots): AnonymousResourceCollection
    {
        return MailshotResource::collection($mailshots);
    }


    public function htmlResponse(LengthAwarePaginator $mailshots, ActionRequest $request): Response
    {
        $actions = [
            [
                'type'  => 'button',
                'style' => 'create',
                'label' => __('mailshot'),
                'route' => [
                    'name'       => 'grp.org.shops.show.marketing.mailshots.create',
                    'parameters' => array_values($request->route()->originalParameters())
                ]
            ]
        ];

        $title = __('mailshots');
        if ($this->parent instanceof Group) {
            $actions = [];
            $title   = __('invite mailshots');
        }

        return Inertia::render(
            'Comms/Mailshots',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                    $this->parent
                ),
                'title'       => $title,
                'pageHead'    => array_filter([
                    'title'   => $title,
                    'actions' => $actions,
                ]),
                'data'        => InviteMailshotsResource::collection($mailshots),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->parent);
    }
}
