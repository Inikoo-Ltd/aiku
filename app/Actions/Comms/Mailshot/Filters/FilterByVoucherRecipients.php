<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 02 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FilterByVoucherRecipients
{
    public function apply(Builder $query, array $filters): Builder
    {
        $filter  = Arr::get($filters, 'voucher_recipients');
        $offerId = is_array($filter) ? ($filter['value'] ?? null) : $filter;

        if (!$offerId) {
            return $query;
        }

        return $query->whereExists(function (Builder $subQuery) use ($offerId) {
            $subQuery->select(DB::raw(1))
                ->from('offer_has_customers')
                ->whereColumn('offer_has_customers.customer_id', 'customers.id')
                ->where('offer_has_customers.offer_id', (int) $offerId);
        });
    }
}
