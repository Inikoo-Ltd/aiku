<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Tue, 19 Nov 2024 11:08:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Eka Yudinata
 */

namespace App\Actions\Comms\Outbox\ReorderRemainder\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Comms\Outbox;
use App\Models\Comms\EmailBulkRun;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexReorderEmailBulkRuns extends OrgAction
{
    public function handle(Outbox $parent, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(EmailBulkRun::class);
        $queryBuilder->leftJoin('email_bulk_run_stats', 'email_bulk_runs.id', '=', 'email_bulk_run_stats.email_bulk_run_id');
        $queryBuilder->where('email_bulk_runs.outbox_id', $parent->id);

        return $queryBuilder
             ->defaultSort('email_bulk_runs.id')
             ->select([
                 'email_bulk_runs.id',
                 'email_bulk_runs.subject',
                 'email_bulk_runs.state',
                 'email_bulk_run_stats.number_dispatched_emails',
                 'email_bulk_run_stats.number_dispatched_emails_state_sent',
                 'email_bulk_run_stats.number_dispatched_emails_state_delivered',
                 'email_bulk_run_stats.number_dispatched_emails_state_hard_bounce',
                 'email_bulk_run_stats.number_dispatched_emails_state_soft_bounce',
                 'email_bulk_run_stats.number_dispatched_emails_state_opened',
                 'email_bulk_run_stats.number_dispatched_emails_state_clicked',
                 'email_bulk_run_stats.number_dispatched_emails_state_spam',
             ])
             ->allowedSorts(['email_bulk_runs.subject', 'email_bulk_runs.state','number_dispatched_emails',
              'number_dispatched_emails_state_sent', 'number_dispatched_emails_state_delivered', 'number_dispatched_emails_state_hard_bounce',
              'number_dispatched_emails_state_soft_bounce', 'number_dispatched_emails_state_opened', 'number_dispatched_emails_state_clicked',
              'number_dispatched_emails_state_spam'])
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
                ->withModelOperations($modelOperations)
                ->column(key: 'state', label: '', type: 'icon')
                ->column(key: 'subject', label: __('subject'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_dispatched_emails', label: __('dispatched'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_dispatched_emails_state_sent', label: __('sent'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_dispatched_emails_state_delivered', label: __('delivered'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_dispatched_emails_state_hard_bounce', label: __('hard bounce'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_dispatched_emails_state_soft_bounce', label: __('soft bounce'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_dispatched_emails_state_opened', label: __('opened'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_dispatched_emails_state_clicked', label: __('clicked'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_dispatched_emails_state_spam', label: __('spam'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

}
