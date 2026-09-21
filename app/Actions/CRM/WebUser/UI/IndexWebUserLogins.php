<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\UI;

use App\InertiaTable\InertiaTable;
use App\Models\CRM\WebUser;
use App\Models\CRM\WebUserLogin;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

class IndexWebUserLogins
{
    use AsAction;

    public function handle(WebUser $webUser, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(WebUserLogin::class)
            ->where('web_user_id', $webUser->id)
            ->defaultSort('-date')
            ->allowedSorts(['date', 'source', 'ip_address'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withEmptyState([
                    'title' => __('No logins recorded yet'),
                    'count' => 0,
                ])
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, type: 'date_hms')
                ->column(key: 'source', label: __('Source'), canBeHidden: false)
                ->column(key: 'ip_address', label: __('IP'), canBeHidden: false)
                ->column(key: 'browser', label: __('Browser'))
                ->column(key: 'os', label: __('OS'))
                ->column(key: 'device', label: __('Device'))
                ->column(key: 'location', label: __('Location'))
                ->defaultSort('-date');
        };
    }
}
