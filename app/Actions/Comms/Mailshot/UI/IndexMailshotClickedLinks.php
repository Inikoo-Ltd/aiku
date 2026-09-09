<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 08 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\WithDispatchedEmailArchiveRead;
use App\Enums\Comms\EmailTrackingEvent\EmailTrackingEventTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Comms\EmailTrackingEvent;
use App\Models\Comms\Mailshot;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexMailshotClickedLinks extends OrgAction
{
    use WithDispatchedEmailArchiveRead;

    public function handle(Mailshot $mailshot, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $connection = $this->dispatchedEmailReadConnection('mailshot_has_dispatched_emails', ['mailshot_id' => $mailshot->id]);

        $queryBuilder = QueryBuilder::for(EmailTrackingEvent::on($connection));

        $queryBuilder
            ->join('mailshot_has_dispatched_emails', 'mailshot_has_dispatched_emails.dispatched_email_id', '=', 'email_tracking_events.dispatched_email_id')
            ->where('mailshot_has_dispatched_emails.mailshot_id', $mailshot->id)
            ->where('email_tracking_events.type', EmailTrackingEventTypeEnum::CLICKED->value)
            ->whereRaw("email_tracking_events.data->>'l' is not null")
            ->selectRaw("email_tracking_events.data->>'l' as url")
            ->selectRaw('count(*) as number_clicks')
            ->selectRaw('count(distinct email_tracking_events.dispatched_email_id) as number_recipients')
            ->groupByRaw("email_tracking_events.data->>'l'");

        return $queryBuilder
            ->defaultSort('-number_clicks')
            ->allowedSorts(['url', 'number_clicks', 'number_recipients'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Mailshot $mailshot, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($mailshot, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->withEmptyState(
                [
                    'title'       => __('No clicks recorded yet'),
                    'description' => __('Once this email goes out, every link people click is listed here.'),
                    'count'       => $mailshot->stats?->number_dispatched_emails ?? 0
                ]
            );

            $table->column(key: 'element', label: __('Element'), canBeHidden: false, sortable: false);
            $table->column(key: 'label', label: __('Link'), canBeHidden: false, sortable: false);
            $table->column(key: 'number_recipients', label: __('People'), canBeHidden: false, sortable: true, align: 'right');
            $table->column(key: 'number_clicks', label: __('Clicks'), canBeHidden: false, sortable: true, align: 'right');
            $table->defaultSort('-number_clicks');
        };
    }
}
