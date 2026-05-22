<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 22 May 2026 15:40:03 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\CRM;

use App\Actions\Traits\WithOrganisationSource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Prospect;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RepairDuplicatedProspect
{
    use AsAction;
    use WithOrganisationSource;

    public function handle(Shop $shop): void
    {

        Prospect::query()
            ->where('prospects.shop_id', $shop->id)
            ->whereNotNull('email')
            ->orderBy('id', 'desc')
            ->chunkById(1000, function ($prospects) {
                foreach ($prospects as $prospect) {

                    $numberProspectsSameEmail = Prospect::where('email', $prospect->email)->where('shop_id', $prospect->shop_id)->count();
                    if ($numberProspectsSameEmail > 1 && $prospect->email) {


                        print "Email: $prospect->email\n";
                        Prospect::where('email', $prospect->email)->where('shop_id', $prospect->shop_id)->get()->each(function ($prospect) {
                            print ">> " . $prospect->id . "  $prospect->slug  \n";
                        });

                        // Keep the newest (first in desc order) and delete the rest
                        $prospectsToKeep = Prospect::where('email', $prospect->email)
                            ->where('shop_id', $prospect->shop_id)
                            ->orderBy('id', 'desc')
                            ->take(1)
                            ->get();

                        $prospectsToDelete = Prospect::where('email', $prospect->email)
                            ->where('shop_id', $prospect->shop_id)
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
        return 'maintenance:repair_duplicated_prospects {shop_slug}';
    }

    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('shop_slug'))->first();

        try {
            $this->handle($shop);
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }


        return 0;
    }
}
