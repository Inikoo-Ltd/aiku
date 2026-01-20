<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 19 Jan 2026 14:35:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\OrgAction;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Helpers\Snapshot\SnapshotBuilderEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;

class IndexPreviousMailshotTemplates extends OrgAction
{
    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Mailshot::class);
        $queryBuilder->join('shops', 'mailshots.shop_id', '=', 'shops.id');
        $queryBuilder->join('emails', 'mailshots.email_id', '=', 'emails.id');
        $queryBuilder->join('snapshots', 'snapshots.id', '=', 'emails.live_snapshot_id');

        $queryBuilder->where('mailshots.shop_id', $shop->id);
        $queryBuilder->where('mailshots.state', MailshotStateEnum::SENT->value);
        $queryBuilder->where('snapshots.builder', SnapshotBuilderEnum::BEEFREE->value);


        return $queryBuilder
            ->defaultSort('-sent_at')
            ->select([
                'mailshots.id',
                'mailshots.state',
                'mailshots.subject',
                'shops.name',
                'mailshots.created_at',
                'mailshots.sent_at'
            ])
            ->allowedSorts(['created_at', 'subject', 'state'])
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
            $table->column(key: 'state', label: 'State', canBeHidden: false, type: 'icon', sortable: true);
            $table->column(key: 'subject', label: 'Subject', canBeHidden: false, sortable: true);
            $table->column(key: 'shop_name', label: __('Shop'), canBeHidden: false, sortable: true);
            $table->column(key: 'sent_at', label: __('Sent At'), canBeHidden: false, sortable: true);
            $table->defaultSort('-sent_at');
        };
    }
}
