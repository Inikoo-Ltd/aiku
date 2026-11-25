<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice;

use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Where a shop's Slovak business customers stand for Peppol e-invoices, from their DIČ and the last register check
 * (VerifyCustomerDic): the meter above the customer list, and the filter behind each of its parts.
 */
class GetCustomersDicStatus
{
    use AsObject;

    public const array STATUSES = ['ok', 'unchecked', 'name_differs', 'not_found', 'differs', 'no_dic'];

    public static function appliesTo(Shop $shop): bool
    {
        return in_array('SK', Arr::get($shop->organisation->settings, 'e_invoicing.countries') ?? [], true);
    }

    /**
     * @return array{total: int, counts: array<string, int>}|null
     */
    public function handle(Shop $shop): ?array
    {
        if (!self::appliesTo($shop)) {
            return null;
        }

        $counts = DB::table('customers')
            ->where('customers.shop_id', $shop->id)
            ->where(fn (Builder $query) => self::scope($query))
            ->selectRaw(self::statusSql().' as dic_status, count(*) as total')
            ->groupBy('dic_status')
            ->pluck('total', 'dic_status')
            ->map(fn ($total) => (int)$total);

        return [
            'total'  => $counts->sum(),
            'counts' => collect(self::STATUSES)->mapWithKeys(fn (string $status) => [$status => $counts->get($status, 0)])->all(),
        ];
    }

    /**
     * @param EloquentBuilder|Builder|\Spatie\QueryBuilder\QueryBuilder $query
     */
    public static function applyFilter(mixed $query, string $status): void
    {
        $query->where(fn ($query) => self::scope($query))
            ->whereRaw(self::statusSql().' = ?', [$status]);
    }

    /**
     * Slovak-address business customers (a company name or an identity number), not deleted.
     */
    private static function scope(EloquentBuilder|Builder $query): void
    {
        $query->whereNull('customers.deleted_at')
            ->whereExists(fn (Builder $address) => $address->selectRaw('1')->from('addresses')
                ->whereColumn('addresses.id', 'customers.address_id')
                ->where('addresses.country_code', 'SK'))
            ->where(fn ($business) => $business->whereRaw("trim(coalesce(customers.company_name, '')) <> ''")
                ->orWhereNotNull('customers.identity_document_number'));
    }

    private static function statusSql(): string
    {
        return "case
            when customers.data->'dic_verification'->>'status' in ('matched', 'filled') then 'ok'
            when customers.data->'dic_verification'->>'status' = 'mismatch' then 'differs'
            when customers.data->'dic_verification'->>'status' in ('name_differs', 'not_refilled') then 'name_differs'
            when customers.data->'dic_verification'->>'status' = 'not_found' then 'not_found'
            when customers.peppol_id like '0245:%'
                or ((customers.identity_document_number collate \"C\") ~ '^[0-9 ]+$'
                    and length(regexp_replace(customers.identity_document_number collate \"C\", '[^0-9]', '', 'g')) between 6 and 8)
                or exists (
                    select 1 from tax_numbers where tax_numbers.owner_type = 'Customer' and tax_numbers.owner_id = customers.id
                    and tax_numbers.country_code = 'SK'
                    and regexp_replace(upper(tax_numbers.number collate \"C\"), '^SK|[^0-9]', '', 'g') ~ '^[1-9][0-9]{9}$'
                    and regexp_replace(upper(tax_numbers.number collate \"C\"), '^SK|[^0-9]', '', 'g')::bigint % 11 = 0
                ) then 'unchecked'
            else 'no_dic' end";
    }
}
