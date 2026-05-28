<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 25 May 2026 16:28:49 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect;

use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckDuplicatedProspect
{
    use AsAction;

    public function getCommandSignature(): string
    {
        return 'prospect:check-duplicates {shop_slug?}';
    }

    public function handle(?Shop $shop = null): Collection
    {

        $emailDuplicates = DB::table('prospects')
            ->selectRaw("shop_id, 'Emails' as duplicate_type, SUM(duplicate_count) as number_of_duplicated_data")
            ->fromSub(function ($query) use ($shop) {
                $query->select('shop_id', 'email')
                    ->selectRaw('COUNT(*) as duplicate_count')
                    ->from('prospects');

                if ($shop) {
                    $query->where('shop_id', $shop->id);
                }

                $query->whereNotNull('email')
                    ->whereNull('deleted_at')
                    ->groupBy('shop_id', 'email')
                    ->havingRaw('COUNT(*) > 1');
            }, 'duplicates')
            ->groupBy('shop_id');

        $phoneDuplicates = DB::table('prospects')
            ->selectRaw("shop_id, 'Phone Numbers' as duplicate_type, SUM(duplicate_count) as number_of_duplicated_data")
            ->fromSub(function ($query) use ($shop) {
                $query->select('shop_id', 'phone')
                    ->selectRaw('COUNT(*) as duplicate_count')
                    ->from('prospects');

                if ($shop) {
                    $query->where('shop_id', $shop->id);
                }

                $query->whereNotNull('phone')
                    ->whereNull('deleted_at')
                    ->groupBy('shop_id', 'phone')
                    ->havingRaw('COUNT(*) > 1');
            }, 'duplicates')
            ->groupBy('shop_id');

        $results = $emailDuplicates
            ->union($phoneDuplicates)
            ->orderBy('shop_id')
            ->orderBy('duplicate_type')
            ->get();

        return $results;
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
            if ($results->isEmpty()) {
                $command->line('No duplicated prospects found.');
                return 0;
            }
            foreach ($results as $result) {
                $shop = Shop::find($result->shop_id);
                // Shop ID: 18 — AWGifts Europe has 10 prospects with duplicated phone numbers
                $command->line("Shop {$result->shop_id}, {$shop?->name} has {$result->number_of_duplicated_data} prospects with duplicated {$result->duplicate_type}");
            }
            return 0;
        } catch (\Exception $e) {
            $command->error("Error checking duplicated prospects: {$e->getMessage()}");
            return 1;
        }
    }
}
