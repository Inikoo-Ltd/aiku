<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 May 2025 18:33:57 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Enums\Comms\Outbox\OutboxCodeEnum;
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
use Spatie\QueryBuilder\AllowedFilter;

trait WithIndexMailshots
{
    public function handleMailshot(?OutboxCodeEnum $outboxCode, Group|Outbox|PostRoom|Organisation|Shop $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('mailshots.subject', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Mailshot::class)
            ->leftJoin('organisations', 'mailshots.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'mailshots.shop_id', '=', 'shops.id')
            ->leftJoin('outboxes', 'mailshots.outbox_id', 'outboxes.id')
            ->leftJoin('mailshot_stats', 'mailshot_stats.mailshot_id', 'mailshots.id')
            ->leftJoin('post_rooms', 'outboxes.post_room_id', 'post_rooms.id');

        if ($outboxCode !== null) {
            $queryBuilder->where('mailshots.type', $outboxCode->value);
        }
        if ($parent instanceof Group) {
            $queryBuilder->where('mailshots.group_id', $parent->id);
        } elseif ($parent instanceof Organisation) {
            $queryBuilder->where('mailshots.organisation_id', $parent->id);
        } elseif ($parent instanceof Shop) {
            $queryBuilder->where('mailshots.shop_id', $parent->id);
        } elseif ($parent instanceof Outbox) {
            $queryBuilder->where('mailshots.outbox_id', $parent->id);
        } elseif ($parent instanceof PostRoom) {
            $queryBuilder->where('outboxes.post_room_id', $parent->id);
        }


        return $queryBuilder
            ->defaultSort('-mailshots.date')
            ->select([
                'mailshots.state',
                'mailshots.date',
                'mailshots.slug',
                'mailshots.id',
                'mailshots.subject',
                'outboxes.slug as outboxes_slug',
                'post_rooms.id as post_room_id',
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
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->allowedSorts(['state', 'subject', 'date', 'number_try_send_success', 'hard_bounce', 'soft_bounce', 'number_deliveries_success', 'opened', 'clicked', 'spam', 'unsubscribed'])
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
                ->column(key: 'state', label: '', type: 'icon')
                ->column(key: 'subject', label: __('subject'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'shop_name', label: __('shop'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'number_try_send_success', label: '', icon: 'fal fa-paper-plane', tooltip: __('Sent emails'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'hard_bounce', label: '', icon: 'fal fa-skull', tooltip: __('Hard Bounces'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'soft_bounce', label: '', icon: 'fal fa-dungeon', tooltip: __('Soft Bounces'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_deliveries_success', label: '', icon: 'fal fa-inbox-in', tooltip: __('Delivered'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'opened', label: '', icon: 'fal fa-envelope-open', tooltip: __('Opened'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'clicked', label: '', icon: 'fal fa-hand-pointer', tooltip: __('Clicked'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'spam', label: '', icon: 'fal fa-dumpster-fire', tooltip: __('Spam'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'unsubscribed', label: '', icon: 'fal fa-thumbs-down', tooltip: __('Unsubscribed'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $mailshots): AnonymousResourceCollection
    {
        return MailshotResource::collection($mailshots);
    }

}
