<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 25 May 2026 13:38:39 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Maintenance\CRM;

use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairDuplicatedProspectPerShop
{
    use AsAction;
    public string $jobQueue = 'low-priority';

    public function tags(): array
    {
        return ['repair_duplicated_prospect_per_shop'];
    }

    public function handle(Shop $shop): void
    {

        // TODO: Make sure order By
        // remove duplicated for phone
        DB::table('prospects')
            ->where('shop_id', $shop->id)
            ->whereNull('deleted_at')
            ->whereNotIn('id', function ($query) use ($shop) {
                $query->selectRaw('DISTINCT ON (phone) id')
                    ->from('prospects')
                    ->where('shop_id', $shop->id)
                    ->whereNotNull('phone')
                    ->whereNull('deleted_at')
                    ->orderByDesc('phone')
                    ->orderByDesc('id')
                    ->unionAll(function ($query) use ($shop) {
                        $query->selectRaw('id')
                            ->from('prospects')
                            ->where('shop_id', $shop->id)
                            ->whereNull('phone')
                            ->whereNull('email')
                            ->whereNull('deleted_at');
                    });

            })
            ->update(['deleted_at' => now()]);

        // remove duplicated for email
        DB::table('prospects')
               ->where('shop_id', $shop->id)
               ->whereNull('deleted_at')
               ->whereNotIn('id', function ($query) use ($shop) {
                   $query->selectRaw('DISTINCT ON (email) id')
                       ->from('prospects')
                       ->where('shop_id', $shop->id)
                       ->whereNotNull('email')
                       ->whereNull('deleted_at')
                       ->orderByDesc('email')
                       ->orderByDesc('id')
                       ->unionAll(function ($query) use ($shop) {
                           $query->selectRaw('id')
                               ->from('prospects')
                               ->where('shop_id', $shop->id)
                               ->whereNull('phone')
                               ->whereNull('email')
                               ->whereNull('deleted_at');
                       });
               })
               ->update(['deleted_at' => now()]);
    }
}
