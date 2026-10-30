<?php

/*
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\EmailContact\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatEmailContact;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The carriers, accountants and suppliers the shop's new emails are written to: its own and the
 * ones saved for every shop of the organisation, as the New email dialog offers them.
 */
class IndexChatEmailContacts extends OrgAction
{
    public function handle(Shop $shop, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $pattern = '%'.addcslashes((string) $value, '%_\\').'%';
            $query->where(fn ($query) => $query->whereLike('chat_email_contacts.name', $pattern)
                ->orWhereLike('chat_email_contacts.email', $pattern)
                ->orWhereLike('chat_email_contacts.company', $pattern));
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(ChatEmailContact::offeredOnShop($shop))
            ->defaultSort('chat_email_contacts.name')
            ->select([
                'chat_email_contacts.id',
                'chat_email_contacts.shop_id',
                'chat_email_contacts.name',
                'chat_email_contacts.email',
                'chat_email_contacts.company',
                'chat_email_contacts.note',
            ])
            ->allowedSorts(['name', 'email', 'company'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No saved contacts'),
                    'description' => __('Add a carrier, the accountants or a supplier here, or tick "Save as a contact" when you send them a new email.'),
                ])
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'company', label: __('Company'), sortable: true)
                ->column(key: 'email', label: __('Email'), canBeHidden: false, sortable: true)
                ->column(key: 'note', label: __('Note'))
                ->column(key: 'all_shops', label: __('Shops'))
                ->column(key: 'actions', label: '', canBeHidden: false);
        };
    }
}
