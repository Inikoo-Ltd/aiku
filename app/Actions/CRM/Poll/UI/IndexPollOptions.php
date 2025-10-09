<?php

namespace App\Actions\CRM\Poll\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Poll;
use App\Models\CRM\PollOption;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPollOptions extends OrgAction
{
    use WithCustomersSubNavigation;
    use WithCRMAuthorisation;


    public function handle(Poll $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('poll_options.label', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PollOption::class);
        $queryBuilder->where('poll_options.poll_id', $parent->id);

        $queryBuilder->leftJoin('poll_option_stats', function ($join) {
            $join->on('poll_options.id', '=', 'poll_option_stats.poll_option_id');
        });

        $selectFields = [
            'poll_options.id',
            'poll_options.slug',
            'poll_options.label',
            'poll_option_stats.number_customers',
        ];

        $groupByFields = ['poll_options.id', 'poll_option_stats.id'];

        $queryBuilder
            ->defaultSort('poll_options.id')
            ->select($selectFields)
            ->groupBy($groupByFields);

        $allowedSorts = ['label', 'number_customers'];
        if ($parent->type === 'referral_sources') {
            $allowedSorts = array_merge($allowedSorts, ['number_customer_purchases', 'total_customer_revenue']);
        }

        return $queryBuilder
            ->allowedSorts($allowedSorts)
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(
        Poll $parent,
        ?array $modelOperations = null,
        $prefix = null,
    ): Closure {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations);

            $table
                ->column(key: 'label', label: __('Label'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_customers', label: __('Customers'), canBeHidden: false, sortable: true, searchable: true);
        };
    }
}
