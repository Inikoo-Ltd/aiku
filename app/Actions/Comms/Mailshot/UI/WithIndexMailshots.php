<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 May 2025 18:33:57 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use App\Models\Comms\PostRoom;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

trait WithIndexMailshots
{
    public function handleMailshot(OutboxCodeEnum $outboxCode, Group|Outbox|PostRoom|Organisation|Shop $parent, $prefix = null): LengthAwarePaginator
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


        $queryBuilder->where('mailshots.type', $outboxCode->value);
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
            ->allowedSorts(['state', 'data', 'subject', 'date', 'sent', 'hard_bounce', 'soft_bounce', 'delivered', 'opened', 'clicked', 'spam'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

}
