<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Sunday, 2 Mar 2026 10:45:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Comms\Mailshot;
use App\Models\Comms\MailshotRecipient;
use App\Models\CRM\Prospect;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;

class IndexProspectMailshotRecipients extends OrgAction
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
            ->leftJoin('prospects', 'mailshot_recipients.recipient_id', '=', 'prospects.id', 'and', 'mailshot_recipients.recipient_type', '=', class_basename(Prospect::class));

        return $queryBuilder
            ->defaultSort('-sent_at')
            ->select([
                'mailshot_recipients.id',
                'dispatched_emails.state',
                'dispatched_emails.sent_at as sent_at',
                'email_addresses.email as email_address',
                'prospects.name as prospect_name',
            ])
            ->allowedSorts(['sent_at', 'email_address'])
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
            $table->column(key: 'prospect_name', label: __('Prospect'), canBeHidden: false, sortable: true);
            $table->column(key: 'email_address', label: __('Email'), canBeHidden: false, sortable: true);
            $table->column(key: 'sent_at', label: __('Sent Date'), canBeHidden: false, sortable: true);

            $table->defaultSort('-sent_at');
        };
    }
}
