<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 8 Jan 2026 11:06:17 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\MailshotRecipient\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Comms\Mailshot;
use App\Models\Comms\MailshotRecipient;
use App\Models\CRM\Customer;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;

class IndexMailshotRecipients extends OrgAction
{
    public function handle(Mailshot $parent, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MailshotRecipient::class);
        $queryBuilder->where('mailshot_recipients.mailshot_id', '=', $parent->id)
            ->leftJoin('dispatched_emails', 'mailshot_recipients.dispatched_email_id', '=', 'dispatched_emails.id')
            ->leftJoin('email_addresses', 'dispatched_emails.email_address_id', '=', 'email_addresses.id')
            ->leftJoin('email_copies', 'dispatched_emails.id', '=', 'email_copies.dispatched_email_id')
            ->leftJoin('customers', 'mailshot_recipients.recipient_id', '=', 'customers.id', 'and', 'mailshot_recipients.recipient_type', '=', class_basename(Customer::class));

        return $queryBuilder
            ->defaultSort('-sent_at')
            ->select([
                'mailshot_recipients.id',
                'mailshot_recipients.recipient_type',
                'mailshot_recipients.recipient_id',
                'mailshot_recipients.channel',
                'mailshot_recipients.created_at',
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
                'customers.name as customer_name',
            ])
            ->allowedSorts(['recipient_type', 'channel', 'sent_at', 'number_email_tracking_events', 'number_reads', 'mask_as_spam', 'number_clicks', 'email_address'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Mailshot $parent, $prefix = null): Closure
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
            $table->column(key: 'recipient_type', label: __('Recipient Type'), canBeHidden: false, sortable: true);
            $table->column(key: 'channel', label: __('Channel'), canBeHidden: false, sortable: true);
            $table->column(key: 'sent_at', label: __('Sent Date'), canBeHidden: false, sortable: true);
            $table->column(key: 'number_email_tracking_events', label: __('Events'), canBeHidden: false, sortable: true);
            $table->column(key: 'number_reads', label: __('Reads'), canBeHidden: false, sortable: true)
                ->column(key: 'number_clicks', label: __('clicks'), canBeHidden: false, sortable: true);
            $table->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable: true);
            $table->defaultSort('-sent_at');
        };
    }
}
