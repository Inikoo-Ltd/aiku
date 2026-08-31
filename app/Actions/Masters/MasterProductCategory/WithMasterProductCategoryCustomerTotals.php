<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait WithMasterProductCategoryCustomerTotals
{
    protected function masterProductCategoryTransactionColumn(MasterProductCategory $masterProductCategory): string
    {
        return match ($masterProductCategory->type) {
            MasterProductCategoryTypeEnum::DEPARTMENT     => 'master_department_id',
            MasterProductCategoryTypeEnum::SUB_DEPARTMENT => 'master_sub_department_id',
            MasterProductCategoryTypeEnum::FAMILY         => 'master_family_id',
        };
    }

    /**
     * Every customer counted on the day of their first ever purchase of this master product category,
     * so any period total is the running sum of the days up to its end.
     *
     * @return array<int, array{organisation_id: int, date: string, customers: int}>
     */
    protected function getCustomerFirstPurchases(MasterProductCategory $masterProductCategory): array
    {
        return Cache::remember(
            'master_product_category:'.$masterProductCategory->id.':customer_first_purchases',
            900,
            function () use ($masterProductCategory) {
                $firstPurchases = DB::table('invoice_transactions')
                    ->where($this->masterProductCategoryTransactionColumn($masterProductCategory), $masterProductCategory->id)
                    ->whereNull('deleted_at')
                    ->whereNotNull('customer_id')
                    ->groupBy('organisation_id', 'customer_id')
                    ->selectRaw('organisation_id, customer_id, MIN(date) as first_date');

                return DB::query()
                    ->fromSub($firstPurchases, 'first_purchases')
                    ->selectRaw('organisation_id, DATE(first_date) as first_day, COUNT(*) as customers')
                    ->groupBy('organisation_id', 'first_day')
                    ->orderBy('first_day')
                    ->get()
                    ->map(fn ($row) => [
                        'organisation_id' => (int) $row->organisation_id,
                        'date'            => (string) $row->first_day,
                        'customers'       => (int) $row->customers,
                    ])
                    ->all();
            }
        );
    }

    /**
     * @param array<int, array{organisation_id: int, date: string, customers: int}> $firstPurchases
     */
    protected function getTotalCustomersUpTo(array $firstPurchases, ?string $date, ?int $organisationId = null): int
    {
        if (!$date) {
            return 0;
        }

        $total = 0;

        foreach ($firstPurchases as $firstPurchase) {
            if ($firstPurchase['date'] > $date) {
                break;
            }

            if ($organisationId !== null && $firstPurchase['organisation_id'] !== $organisationId) {
                continue;
            }

            $total += $firstPurchase['customers'];
        }

        return $total;
    }
}
