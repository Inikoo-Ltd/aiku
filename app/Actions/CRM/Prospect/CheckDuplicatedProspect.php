<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 25 May 2026 16:28:49 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect;

use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckDuplicatedProspect
{
    use AsAction;

    public function getCommandSignature(): string
    {
        return 'crm:prospect:check-duplicates {shop_slug?}';
    }

    public function handle(?Shop $shop = null): array
    {
        $query = "
            SELECT 
                shop_id,
                SUM(duplicate_count) as number_of_duplicated_data
            FROM (
                SELECT 
                    shop_id,
                    email,
                    COUNT(*) as duplicate_count
                FROM prospects
                WHERE email IS NOT null 
                and deleted_at is null
                GROUP BY shop_id, email
                HAVING COUNT(*) > 1
            ) as duplicates
            GROUP BY shop_id
            ORDER BY shop_id
        ";

        if ($shop) {
            $query = "
                SELECT 
                    shop_id,
                    SUM(duplicate_count) as number_of_duplicated_data
                FROM (
                    SELECT 
                        shop_id,
                        email,
                        COUNT(*) as duplicate_count
                    FROM prospects
                    WHERE shop_id = {$shop->id}
                    AND email IS NOT null 
                    and deleted_at is null
                    GROUP BY shop_id, email
                    HAVING COUNT(*) > 1
                ) as duplicates
                GROUP BY shop_id
                ORDER BY shop_id
            ";
        }

        return DB::select($query);
    }

    public function asCommand(Command $command): int
    {
        $shopSlug = $command->argument('shop_slug');
        $shop = null;

        $command->info('Checking for duplicated prospects...');

        if ($shopSlug !== null) {
            $shop = Shop::where('slug', $shopSlug)->first();

            if ($shop === null) {
                $command->error("Shop with slug '{$shopSlug}' not found.");
                return 1;
            }

            $command->line("Checking duplicated prospects for shop: {$shop->name}");
        } else {
            $command->line("Checking duplicated prospects for all shops...");
        }

        try {
            $results = $this->handle($shop);

            if (empty($results)) {
                $command->info('No duplicated prospects found.');
                return 0;
            }
            $command->info(json_encode($results, JSON_PRETTY_PRINT));

            // $command->table(
            //     ['Shop ID', 'Number of Duplicated Data'],
            //     $results
            // );

            // $totalDuplicates = array_sum(array_column($results, 'number_of_duplicated_data'));
            // $command->info("Total duplicated prospects across all shops: {$totalDuplicates}");

            return 0;
        } catch (\Exception $e) {
            $command->error("Error checking duplicated prospects: {$e->getMessage()}");
            return 1;
        }
    }
}
