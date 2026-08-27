<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Oct 2024 00:23:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\History\UI;

use App\Enums\Helpers\Audit\AuditEventEnum;
use Carbon\Carbon;
use App\InertiaTable\InertiaTable;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use App\Models\Helpers\Audit;
use Spatie\QueryBuilder\AllowedFilter;
use Throwable;

class IndexHistory
{
    use AsAction;
    use WithAttributes;

    public string $model;

    public function handle($model, $prefix = null, mixed $eventScopeFilter = null, mixed $excludeEventScopeFilter = null, mixed $userScopeFilter = null): LengthAwarePaginator|array|bool
    {
        $this->model = class_basename($model);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('event', 'ILIKE', "%{$value}%")
                    ->orWhereRaw('old_values::text ILIKE ?', ["%{$value}%"])
                    ->orWhereRaw('new_values::text ILIKE ?', ["%{$value}%"])
                    ->orWhere(function ($query) use ($value) {
                        $query->where('user_type', 'User')
                            ->whereIn('user_id', DB::table('users')->select('id')->where('contact_name', 'ILIKE', "%{$value}%"));
                    });
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Audit::on($this->auditReadConnection($model)));

        $queryBuilder->orderBy('id', 'DESC');
        $queryBuilder->where('auditable_type', $this->model);
        $queryBuilder->where('event', '!=', AuditEventEnum::CUSTOMER_NOTE->value);

        if ($eventScopeFilter !== null) {
            $queryBuilder->when(
                is_array($eventScopeFilter),
                fn ($query) => $query->whereIn('event', $eventScopeFilter),
                fn ($query) => $query->where('event', $eventScopeFilter)
            );
        }

        if ($excludeEventScopeFilter !== null) {
            $queryBuilder
                ->when(
                    is_array($excludeEventScopeFilter),
                    fn ($query) => $query->whereNotIn('event', $excludeEventScopeFilter),
                    fn ($query) => $query->where('event', '!=', $excludeEventScopeFilter)
                );
        }

        if ($userScopeFilter) {
            $queryBuilder
                ->when(
                    is_array($userScopeFilter),
                    fn ($query) => $query->whereIn('user_type', $userScopeFilter),
                    fn ($query) => $query->where('user_type', $userScopeFilter)
                );
        }

        if (isset($model->id)) {
            $queryBuilder->where('auditable_id', $model->id);
        }

        return $queryBuilder
            ->defaultSort('audits.created_at')
            ->allowedSorts(['ip_address','auditable_id', 'auditable_type', 'user_type', 'url','created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * Audits of closed shops and discontinued products/org stocks live in the archive database
     * (see ArchiveAudits). When the operational database has no rows for this record but the
     * archive does, the whole listing is served from the archive — a dead record's trail moves
     * wholesale, so there is no mixed page. A record that came back to life (reopened shop,
     * relaunched product) starts a clean live trail; its archived history stays reachable and the
     * footer note says it exists, so the clean restart never reads as vanished history.
     *
     * The archive lives on another server, so it is never allowed to break a page: any failure
     * reaching it degrades to the operational database. The footer note is passed to
     * tableStructure through request attributes because its many callers wire the two
     * independently within the same request.
     */
    private function auditReadConnection($model): ?string
    {
        if (!isset($model->id) || !config('database.connections.archive.database')) {
            return null;
        }

        $where = ['auditable_type' => $this->model, 'auditable_id' => $model->id];

        try {
            $archived = DB::connection('archive')->table('audits')
                ->where($where)
                ->selectRaw('count(*) as total, max(created_at) as latest')
                ->first();

            if (!$archived || !$archived->total) {
                return null;
            }

            if (!DB::table('audits')->where($where)->exists()) {
                request()->attributes->set('history_archive_note', __('Showing archived history.'));

                return 'archive';
            }

            request()->attributes->set(
                'history_archive_note',
                __(':count older entries up to :date are archived.', [
                    'count' => number_format($archived->total),
                    'date'  => Carbon::parse($archived->latest)->format('j M Y'),
                ])
            );

            return null;
        } catch (Throwable $exception) {
            if (app()->runningUnitTests()) {
                throw $exception;
            }
            Log::warning('Archive database unreachable, serving live history only: '.$exception->getMessage());

            return null;
        }
    }

    public function tableStructure($prefix = null, ?array $exportLinks = null): Closure
    {
        return function (InertiaTable $table) use ($exportLinks, $prefix) {

            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($archiveNote = request()->attributes->get('history_archive_note')) {
                $table->withFooterNote($archiveNote);
            }

            $table
                ->withGlobalSearch()
                ->withExportLinks($exportLinks)
                ->column(key: 'datetime', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'user_name', label: __('User'), canBeHidden: false, sortable: true)
                ->column(key: 'values', label: '', canBeHidden: false)
                ->defaultSort('ip_address');
        };
    }
}
