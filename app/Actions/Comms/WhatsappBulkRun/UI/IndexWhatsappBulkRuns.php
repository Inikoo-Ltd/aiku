<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappBulkRun\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Comms\WhatsappBulkRun;
use App\Models\Comms\WhatsappOutbox;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexWhatsappBulkRuns extends OrgAction
{
    public function handle(WhatsappOutbox $whatsappOutbox, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(WhatsappBulkRun::class)
            ->where('whatsapp_bulk_runs.whatsapp_outbox_id', $whatsappOutbox->id)
            ->defaultSort('-whatsapp_bulk_runs.created_at')
            ->select([
                'whatsapp_bulk_runs.id',
                'whatsapp_bulk_runs.state',
                'whatsapp_bulk_runs.recipients_count',
                'whatsapp_bulk_runs.number_sent',
                'whatsapp_bulk_runs.number_failed',
                'whatsapp_bulk_runs.created_at',
                'whatsapp_bulk_runs.sent_at',
            ])
            ->allowedSorts([
                'created_at'       => 'whatsapp_bulk_runs.created_at',
                'recipients_count' => 'whatsapp_bulk_runs.recipients_count',
                'number_sent'      => 'whatsapp_bulk_runs.number_sent',
                'number_failed'    => 'whatsapp_bulk_runs.number_failed',
            ])
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

            $table
                ->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'state', label: __('State'), canBeHidden: false)
                ->column(key: 'recipients_count', label: __('Recipients'), canBeHidden: false, sortable: true, type: 'number')
                ->column(key: 'number_sent', label: __('Sent'), canBeHidden: false, sortable: true, type: 'number')
                ->column(key: 'number_failed', label: __('Failed'), canBeHidden: false, sortable: true, type: 'number')
                ->column(key: 'sent_at', label: __('Finished'), canBeHidden: false)
                ->defaultSort('-created_at');
        };
    }
}
