<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 22 May 2026 15:40:03 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\CRM;

use App\Models\Catalogue\Shop;
use App\Models\CRM\Prospect;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RepairDuplicateEmailProspect
{
    use AsAction;

    public function handle(?Shop $shop = null): void
    {
        $query = Prospect::query()
            ->whereNotNull('email')
            ->orderBy('id', 'desc');

        // Filter by shop if provided
        if ($shop !== null) {
            $query->where('prospects.shop_id', $shop->id);
        }

        $query->chunkById(1000, function ($prospects) {
            foreach ($prospects as $prospect) {

                $email = $prospect->email;
                $shopId = $prospect->shop_id;

                $numberProspectsSameEmail = Prospect::where('email', $email)
                    ->where('shop_id', $shopId)
                    ->count();

                if ($numberProspectsSameEmail > 1) {

                    print "Email: $email (Shop ID: {$shopId})\n";
                    Prospect::where('email', $email)
                        ->where('shop_id', $shopId)
                        ->get()
                        ->each(function ($prospect) {
                            print ">> " . $prospect->id . "  $prospect->slug  \n";
                        });

                    // Keep the newest (first in desc order) and delete the rest
                    $prospectsToKeep = Prospect::where('email', $email)
                        ->where('shop_id', $shopId)
                        ->orderBy('id', 'desc')
                        ->take(1)
                        ->get();

                    $prospectsToDelete = Prospect::where('email', $email)
                        ->where('shop_id', $shopId)
                        ->whereNotIn('id', $prospectsToKeep->pluck('id'))
                        ->get();

                    $prospectsToDelete->each(function ($prospect) {
                        print "Soft-deleting duplicate prospect ID: {$prospect->id}\n";
                        $prospect->delete(); // This will set deleted_at timestamp due to SoftDeletes trait
                    });
                }
            }
        }, 'id');
    }


    public function getCommandSignature(): string
    {
        return 'maintenance:repair_duplicated_prospects {shop_slug?}';
    }

    public function asCommand(Command $command): int
    {
        $shopSlug = $command->argument('shop_slug');
        $shop = null;

        // If shop_slug is provided, fetch the shop
        if ($shopSlug !== null) {
            $shop = Shop::where('slug', $shopSlug)->first();

            if ($shop === null) {
                $command->error("Shop with slug '{$shopSlug}' not found.");
                return 1;
            }

            $command->line("Processing duplicated prospects for shop: {$shop->slug}");
        } else {
            $command->line("Processing duplicated prospects for all shops...");
        }

        try {
            $this->handle($shop);
            $command->info("Repair completed successfully!");
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
