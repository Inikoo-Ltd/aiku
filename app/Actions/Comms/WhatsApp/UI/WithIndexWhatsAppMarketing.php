<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 19 May 2026 16:19:01 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsApp\UI;

use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

trait WithIndexWhatsAppMarketing
{
    public function handleWhatsAppMarketing(Shop $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('whatsapp_marketing_campaigns.name', 'like', "%{$value}%")
                    ->orWhere('whatsapp_marketing_campaigns.subject', 'like', "%{$value}%");
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Mailshot::class);

        // TODO: Replace with actual WhatsApp marketing campaigns table when available
        // For now, return an empty paginator
        return  $queryBuilder->select([
            'mailshots.state',
            'mailshots.date',
            'mailshots.slug'
        ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'state', label: '', type: 'icon')
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'subject', label: __('Subject'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'sent', label: '', icon: 'fal fa-paper-plane', tooltip: __('Sent'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'delivered', label: '', icon: 'fal fa-inbox-in', tooltip: __('Delivered'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'opened', label: '', icon: 'fal fa-envelope-open', tooltip: __('Opened'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'clicked', label: '', icon: 'fal fa-hand-pointer', tooltip: __('Clicked'), canBeHidden: false, sortable: true, searchable: true);
        };
    }
}
