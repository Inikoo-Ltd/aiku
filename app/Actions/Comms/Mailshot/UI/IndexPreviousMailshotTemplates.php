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
use Spatie\QueryBuilder\AllowedFilter;

class IndexPreviousMailshotTemplates extends OrgAction
{
    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('mailshots.subject', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Mailshot::class);
        $queryBuilder->join('shops', 'mailshots.shop_id', '=', 'shops.id');
        $queryBuilder->join('emails', 'mailshots.id', '=', 'emails.parent_id')
            ->where('emails.parent_type', class_basename(Mailshot::class));
        $queryBuilder->join('snapshots', 'snapshots.id', '=', 'emails.live_snapshot_id');

        $queryBuilder->where('mailshots.shop_id', $shop->id);
        $queryBuilder->where('mailshots.state', MailshotStateEnum::SENT->value);
        $queryBuilder->where('snapshots.builder', SnapshotBuilderEnum::BEEFREE->value);
        $queryBuilder->whereNotNull('mailshots.sent_at');


        return $queryBuilder
            ->defaultSort('-sent_at')
            ->select([
                'mailshots.id',
                'mailshots.state',
                'mailshots.subject',
                'shops.name as shop_name',
                'mailshots.created_at',
                'mailshots.sent_at'
            ])
            ->allowedSorts(['created_at', 'subject', 'sent_at'])
            ->allowedFilters([$globalSearch])
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
            $table->withGlobalSearch();
            $table->column(key: 'shop_name', label: __('Shop'), canBeHidden: false, sortable: true);
            $table->column(key: 'subject', label: __('Subject'), canBeHidden: false, sortable: true);
            $table->column(key: 'sent_at', label: __('Sent At'), canBeHidden: false, sortable: true);
            $table->column(key: 'actions', label: __('Action'));
            $table->defaultSort('-sent_at');
        };
    }
}
