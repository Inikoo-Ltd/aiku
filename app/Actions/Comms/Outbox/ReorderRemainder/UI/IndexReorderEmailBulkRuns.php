<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:08:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox\ReorderRemainder\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use App\Models\Comms\EmailBulkRun;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Support\Facades\Log;

class IndexReorderEmailBulkRuns extends OrgAction
{
    private Shop|Outbox $parent;

    public function handle(Shop|Outbox $parent, $prefix = null): LengthAwarePaginator
    {
        Log::info("handle ".get_class($parent));
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(EmailBulkRun::class);
        $queryBuilder->leftJoin('organisations', 'email_bulk_runs.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'email_bulk_runs.shop_id', '=', 'shops.id');
        if ($parent instanceof Outbox) {
            $queryBuilder->where('email_bulk_runs.outbox_id', $parent->id);
        } elseif ($parent instanceof Shop) {
            $queryBuilder->where('email_bulk_runs.shop_id', $parent->id);
        }

        return $queryBuilder
            ->defaultSort('email_bulk_runs.id')
            ->select([
                'email_bulk_runs.id',
                'email_bulk_runs.subject',
                'email_bulk_runs.state',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->allowedSorts(['email_bulk_runs.subject', 'email_bulk_runs.state', 'shop_name', 'organisation_name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        Log::info("tableStructure ".get_class($parent));
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->column(key: 'state', label: '', type: 'icon')
                ->column(key: 'subject', label: __('subject'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

}
