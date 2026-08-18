<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:08:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\DispatchedEmail\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\WithDispatchedEmailArchiveRead;
use App\InertiaTable\InertiaTable;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Services\QueryBuilder;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Ordering\Order;

class IndexDispatchedEmails extends OrgAction
{
    use WithDispatchedEmailArchiveRead;

    public function handle(Mailshot|Outbox|Customer|Prospect $parent, $prefix = null): LengthAwarePaginator
    {
        /*
         * A customer collects emails over years, so its history straddles the retention window
         * permanently and is never switched wholesale: the archived ones are summoned on request
         * from the footer note instead, which keeps what is on screen unambiguous. A mailshot is a
         * single send, so it is either entirely live or entirely archived.
         */
        $connection = match (true) {
            request()->boolean('archived') && config('database.connections.archive.database') => 'archive',
            $parent instanceof Mailshot => $this->dispatchedEmailReadConnection('mailshot_has_dispatched_emails', ['mailshot_id' => $parent->id]),
            default => null,
        };

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->orWhereWith('email_addresses.email', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(DispatchedEmail::on($connection));
        $queryBuilder->leftJoin('email_addresses', 'dispatched_emails.email_address_id', '=', 'email_addresses.id');

        switch (class_basename($parent)) {
            case 'Customer':
                $queryBuilder->join('customer_has_dispatched_emails', 'customer_has_dispatched_emails.dispatched_email_id', '=', 'dispatched_emails.id');
                $queryBuilder->where('customer_has_dispatched_emails.customer_id', $parent->id);
                break;
            case 'Outbox':
                /*
                * Check the outbox code in the following list of codes
                */
                if (in_array($parent->code, [
                    OutboxCodeEnum::BASKET_LOW_STOCK,
                    OutboxCodeEnum::DELIVERY_CONFIRMATION,
                    OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER,
                    OutboxCodeEnum::REORDER_REMINDER,
                    OutboxCodeEnum::REORDER_REMINDER_2ND,
                    OutboxCodeEnum::REORDER_REMINDER_3RD,
                    OutboxCodeEnum::ORDER_CONFIRMATION
                ])) {
                    $queryBuilder->leftJoin('customer_has_dispatched_emails', 'customer_has_dispatched_emails.dispatched_email_id', '=', 'dispatched_emails.id');

                    // for fulfilment customer
                    if ($parent->fulfilment_id) {
                        $queryBuilder->leftJoin('fulfilment_customers', function ($join) {
                            $join->on('fulfilment_customers.customer_id', '=', 'customer_has_dispatched_emails.customer_id');
                        });
                    }
                    $queryBuilder->leftJoin('model_has_dispatched_emails', function ($join) {
                        $join->on('model_has_dispatched_emails.dispatched_email_id', '=', 'dispatched_emails.id')
                            ->where('model_has_dispatched_emails.model_type', '=', class_basename(Order::class));
                    });
                }
                $queryBuilder->where('dispatched_emails.outbox_id', $parent->id);
                break;
            case 'Mailshot':
                $queryBuilder->leftJoin('mailshot_has_dispatched_emails', 'mailshot_has_dispatched_emails.dispatched_email_id', '=', 'dispatched_emails.id');
                $queryBuilder->where('mailshot_id', $parent->id);
                $queryBuilder->leftJoin('test_email_recipient_has_dispatched_emails', 'test_email_recipient_has_dispatched_emails.dispatched_email_id', '=', 'dispatched_emails.id');
                $queryBuilder->whereNull('test_email_recipient_has_dispatched_emails.dispatched_email_id');
                break;
            case 'Prospect':
                $queryBuilder->join('prospect_has_dispatched_emails', 'prospect_has_dispatched_emails.dispatched_email_id', '=', 'dispatched_emails.id');
                $queryBuilder->where('prospect_has_dispatched_emails.prospect_id', $parent->id);
                break;
            default:
                abort(404);
        }


        $selectColumns = [
            'dispatched_emails.id',
            'dispatched_emails.state',
            'dispatched_emails.mask_as_spam',
            'dispatched_emails.number_email_tracking_events',
            'email_addresses.email as email_address',
            'dispatched_emails.sent_at as sent_at',
            'dispatched_emails.number_reads',
            'dispatched_emails.number_clicks',
            'dispatched_emails.outbox_id',
        ];

        if ($parent instanceof Outbox) {
            if (in_array($parent->code, [
                OutboxCodeEnum::BASKET_LOW_STOCK,
                OutboxCodeEnum::DELIVERY_CONFIRMATION,
                OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER,
                OutboxCodeEnum::REORDER_REMINDER,
                OutboxCodeEnum::REORDER_REMINDER_2ND,
                OutboxCodeEnum::REORDER_REMINDER_3RD,
                OutboxCodeEnum::ORDER_CONFIRMATION
            ])) {
                $selectColumns = array_merge(
                    $selectColumns,
                    [
                        'customer_has_dispatched_emails.customer_id as customer_id',
                        'model_has_dispatched_emails.model_id as order_id',
                    ]
                );
                if ($parent->fulfilment_id) {
                    $selectColumns = array_merge(
                        $selectColumns,
                        [
                            'fulfilment_customers.id as fulfilment_customer_id',
                            'fulfilment_customers.slug as fulfilment_customer_slug'
                        ]
                    );
                }
            }
        }


        return $queryBuilder
            ->defaultSort('-sent_at')
            ->select($selectColumns)
            ->allowedSorts(['email_address', 'number_email_tracking_events', 'sent_at', 'number_reads', 'mask_as_spam', 'number_clicks'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * A customer's older emails live on the archive server, so they are offered rather than mixed in:
     * the counts come from what the archiver recorded when it moved them, which keeps the note free
     * of any cross-server query.
     */
    private function addArchivedEmailsNote(InertiaTable $table, Customer $customer): void
    {
        if (request()->boolean('archived')) {
            $table->withFooterNote(
                __('Showing archived emails.'),
                ['label' => __('Back to recent emails'), 'href' => request()->fullUrlWithoutQuery(['archived'])]
            );

            return;
        }

        $archived = $customer->stats?->archived_dispatched_emails;
        $count    = $archived['number_dispatched_emails'] ?? 0;

        if (!$count) {
            return;
        }

        $table->withFooterNote(
            __(':count emails before :date are archived.', [
                'count' => number_format($count),
                'date'  => Carbon::parse($archived['last_dispatched_email_at'])->format('j M Y'),
            ]),
            ['label' => '🧟 '.__('Summon them'), 'href' => request()->fullUrlWithQuery(['archived' => 1])]
        );
    }

    public function tableStructure($parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'state', label: '', canBeHidden: false, type: 'icon');
            $table->column(key: 'email_address', label: __('Email'), canBeHidden: false, sortable: true);

            if ($parent instanceof Customer) {
                $table->column(key: 'outbox_code', label: __('Type'), canBeHidden: false);
            }

            $table->column(key: 'sent_at', label: __('Sent Date'), canBeHidden: false, sortable: true);

            if ($parent instanceof Outbox) {
                if (in_array($parent->code, [
                    OutboxCodeEnum::BASKET_LOW_STOCK,
                    OutboxCodeEnum::DELIVERY_CONFIRMATION,
                    OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER,
                    OutboxCodeEnum::REORDER_REMINDER,
                    OutboxCodeEnum::REORDER_REMINDER_2ND,
                    OutboxCodeEnum::REORDER_REMINDER_3RD,
                    OutboxCodeEnum::ORDER_CONFIRMATION

                ])) {
                    $table->column(key: 'customer_name', label: __('Customer'), canBeHidden: false);
                    $table->column(key: 'order_slug', label: __('Order'), canBeHidden: false);
                }
            }
            $table->column(key: 'number_email_tracking_events', label: __('events'), canBeHidden: false, sortable: true);
            $table->column(key: 'number_reads', label: __('reads'), canBeHidden: false, sortable: true)
                ->column(key: 'number_clicks', label: __('clicks'), canBeHidden: false, sortable: true);
            $table->defaultSort('-sent_at');

            if ($parent instanceof Customer) {
                $this->addArchivedEmailsNote($table, $parent);
            }
        };
    }
}
