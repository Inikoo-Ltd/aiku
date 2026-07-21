<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Jul 2026 09:10:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\ClockingMachineQRCode;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexClockingMachineQRCodes extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    public function handle(ClockingMachine $clockingMachine, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function (Builder $query, string $value) {
            $query->where(function (Builder $query) use ($value) {
                $query->whereStartWith('clocking_machine_qr_codes.label', $value)
                    ->orWhereStartWith('clocking_machine_qr_codes.hash', $value);
            });
        });

        return QueryBuilder::for(ClockingMachineQRCode::class)
            ->with('clockingMachine.organisation')
            ->where('clocking_machine_qr_codes.clocking_machine_id', $clockingMachine->id)
            ->defaultSort('-created_at')
            ->allowedSorts([
                'label',
                'hash',
                'active',
                AllowedSort::field('active_badge', 'active'),
                'deactivated_at',
                'number_clockings',
                'number_different_staff',
                'last_used_at',
                'created_at',
            ])
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
                ->withLabelRecord([__('QR code'), __('QR codes')])
                ->column(key: 'label', label: __('Label'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'hash', label: __('Hash'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'active_badge', label: __('Active'), canBeHidden: false, sortable: true, type: 'badge')
                ->column(key: 'deactivated_at', label: __('Deactivated at'), sortable: true)
                ->column(key: 'number_clockings', label: __('Clockings'), canBeHidden: false, sortable: true)
                ->column(key: 'number_different_staff', label: __('Staff'), canBeHidden: false, sortable: true)
                ->column(key: 'last_used_at', label: __('Last used'), sortable: true)
                ->column(key: 'created_at', label: __('Created at'), sortable: true)
                ->column(key: 'actions', label: __('Actions'), canBeHidden: false)
                ->defaultSort('-created_at');
        };
    }
}
