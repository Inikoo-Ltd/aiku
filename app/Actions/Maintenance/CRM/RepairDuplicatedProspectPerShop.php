<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 25 May 2026 13:38:39 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Maintenance\CRM;

use App\Models\Catalogue\Shop;
use App\Models\CRM\Prospect;
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
        // Check if there are any duplicated records before running the main process
        $totalDuplicatedRecords = $this->countDuplicatedRecords($shop);

        // If no duplicated records found, skip the process
        if ($totalDuplicatedRecords == 0) {
            return;
        }

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

    private function countDuplicatedRecords(Shop $shop): int
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) as total_duplicated_records
            FROM (
                SELECT email
                FROM prospects
                WHERE shop_id = ?
                    AND email IS NOT NULL
                    AND deleted_at IS NULL
                GROUP BY email
                HAVING COUNT(*) > 1
            ) as duplicates',
            [$shop->id]
        );

        return $result->total_duplicated_records ?? 0;
    }
}
