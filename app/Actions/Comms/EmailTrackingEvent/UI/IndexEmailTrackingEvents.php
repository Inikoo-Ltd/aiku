<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 21-02-2025, Bali, Indonesia
 * GitHub: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Comms\EmailTrackingEvent\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\EmailTrackingEvent;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexEmailTrackingEvents extends OrgAction
{
    public function handle(DispatchedEmail $dispatchedEmail, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(EmailTrackingEvent::class);

        $queryBuilder->where('dispatched_email_id', $dispatchedEmail->id);


        return $queryBuilder
            ->defaultSort('-date')
            ->select([
                'email_tracking_events.type',
                'email_tracking_events.data',
                'email_tracking_events.ip',
                'email_tracking_events.device',
                'email_tracking_events.created_at as date',
            ])
            ->allowedSorts(['type', 'data', 'device', 'ip', 'date'])
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

            $table->column(key: 'type', label: '', canBeHidden: false, type: 'icon');
            $table->column(key: 'ip', label: __('Ip Address'), canBeHidden: false, sortable: true);
            $table->column(key: 'device', label: __('Device'), canBeHidden: false, sortable: true);
            $table->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true);
            $table->defaultSort('-date');
        };
    }

}
