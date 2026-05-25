<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 25 May 2026 13:38:39 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Maintenance\CRM;

use App\Models\Catalogue\Shop;
use App\Models\CRM\Prospect;
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
        $query = Prospect::query()
            ->where('shop_id', $shop->id)
            ->whereNotNull('email')
            ->orderBy('id', 'desc');

        $query->chunkById(1000, function ($prospects) {
            foreach ($prospects as $prospect) {

                $email = $prospect->email;
                $shopId = $prospect->shop_id;

                $numberProspectsSameEmail = Prospect::where('email', $email)
                    ->where('shop_id', $shopId)
                    ->count();

                if ($numberProspectsSameEmail > 1) {
                    // Keep the newest (first in desc order) and delete the rest
                    $prospectsToKeep = Prospect::where('email', $email)
                        ->where('shop_id', $shopId)
                        ->orderBy('id', 'desc')
                        ->take(1)
                        ->get();

                    Prospect::where('email', $email)
                        ->where('shop_id', $shopId)
                        ->whereNotIn('id', $prospectsToKeep->pluck('id'))
                        ->delete();
                }
            }
        });
    }
}
