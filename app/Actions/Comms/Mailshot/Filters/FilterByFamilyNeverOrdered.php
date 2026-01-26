<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;

class FilterByFamilyNeverOrdered
{
    /**
     * Apply the "By Family Never Ordered" filter to the query.
     *
     * @param Builder|SpatieQueryBuilder $query
     * @param int|string $familyId
     * @return Builder|SpatieQueryBuilder
     */
    public function apply($query, $familyId)
    {
        if (empty($familyId)) {
            return $query;
        }

        $familyIds = is_array($familyId) ? $familyId : [$familyId];

        $query->whereNotExists(function ($subQuery) use ($familyIds) {
            $subQuery->select(DB::raw(1))
                ->from('invoice_transactions')
                ->join('orders', 'orders.id', '=', 'invoice_transactions.order_id')
                ->whereColumn('invoice_transactions.customer_id', 'customers.id')
                ->whereIn('invoice_transactions.family_id', $familyIds)
                ->whereNull('invoice_transactions.deleted_at');
        });

        return $query;
    }
}
