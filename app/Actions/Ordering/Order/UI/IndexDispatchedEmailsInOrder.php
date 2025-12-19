<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:08:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Comms\DispatchedEmail;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;

class IndexDispatchedEmailsInOrder extends OrgAction
{
    public function handle(Order $parent, $prefix = null): LengthAwarePaginator
    {

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(DispatchedEmail::class);
        $queryBuilder->join('model_has_dispatched_emails', function ($join) use ($parent) {
            $join->on('dispatched_emails.id', '=', 'model_has_dispatched_emails.dispatched_email_id')
                ->where('model_has_dispatched_emails.model_type', '=', class_basename($parent))
                ->where('model_has_dispatched_emails.model_id', '=', $parent->id);
        })
            ->leftJoin('email_addresses', 'dispatched_emails.email_address_id', '=', 'email_addresses.id')
            ->leftJoin('email_copies', 'dispatched_emails.id', '=', 'email_copies.dispatched_email_id');

        return $queryBuilder
            ->defaultSort('-sent_at')
            ->select([
                'dispatched_emails.id',
                'dispatched_emails.state',
                'dispatched_emails.mask_as_spam',
                'dispatched_emails.number_email_tracking_events',
                'dispatched_emails.sent_at as sent_at',
                'dispatched_emails.number_reads',
                'dispatched_emails.number_clicks',
                'email_addresses.email as email_address',
                'email_copies.subject as subject',
                'email_copies.body as body_preview',
                'email_copies.is_body_encoded',
            ])
            ->allowedSorts(['number_email_tracking_events', 'sent_at', 'number_reads', 'mask_as_spam', 'number_clicks', 'email_addresses.email_address'])
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
            $table->column(key: 'state', label: 'State', canBeHidden: false, type: 'icon');
            $table->column(key: 'subject', label: 'Subject', canBeHidden: false, sortable: true);
            $table->column(key: 'email_address', label: __('Email'), canBeHidden: false, sortable: true);
            $table->column(key: 'sent_at', label: __('Sent Date'), canBeHidden: false, sortable: true);
            $table->column(key: 'number_email_tracking_events', label: __('Events'), canBeHidden: false, sortable: true);
            $table->column(key: 'number_reads', label: __('Reads'), canBeHidden: false, sortable: true)
                ->column(key: 'number_clicks', label: __('clicks'), canBeHidden: false, sortable: true);
            $table->defaultSort('-sent_at');
        };
    }
}
