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
        $query = DB::table('prospects as p')
            ->join('shops as s', 'p.shop_id', '=', 's.id')
            ->select(
                'p.shop_id',
                's.name as shop_name',
                'p.email',
                'p.phone'
            )
            ->whereNull('p.deleted_at');

        if ($shop) {
            $query->where('p.shop_id', $shop->id);
        }

        $query->where(function ($q) {
            $q->whereIn('p.email', function ($sub) {
                $sub->select('email')
                    ->from('prospects')
                    ->whereNotNull('email')
                    ->whereNull('deleted_at')
                    ->groupBy('shop_id', 'email')
                    ->havingRaw('COUNT(*) > 1');
            })->orWhereIn('p.phone', function ($sub) {
                $sub->select('phone')
                    ->from('prospects')
                    ->whereNotNull('phone')
                    ->whereNull('deleted_at')
                    ->groupBy('shop_id', 'phone')
                    ->havingRaw('COUNT(*) > 1');
            });
        });

        return $query
            ->orderBy('p.shop_id')
            ->orderBy('p.email')
            ->orderBy('p.phone')
            ->get();
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
                $command->line(
                    "Shop ID: {$result->shop_id} | " .
                    "Shop: {$result->shop_name} | " .
                    "Email: {$result->email} | " .
                    "Phone: {$result->phone}"
                );
            }

            return 0;
        } catch (\Exception $e) {
            $command->error("Error checking duplicated prospects: {$e->getMessage()}");
            return 1;
        }
    }
}
