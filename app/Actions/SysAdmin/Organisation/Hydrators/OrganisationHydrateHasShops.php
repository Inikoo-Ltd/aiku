<?php

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateHasShops
{
    use AsAction;

    public string $commandSignature = 'hydrate:organisation_has_shops {--s|slug= : Organisation slug}';

    public function handle(Organisation $organisation): void
    {
        $hasFulfilment = DB::table('shops')
            ->where('organisation_id', $organisation->id)
            ->where('type', ShopTypeEnum::FULFILMENT->value)
            ->exists();

        $hasDropshipping = DB::table('shops')
            ->where('organisation_id', $organisation->id)
            ->where('type', ShopTypeEnum::DROPSHIPPING->value)
            ->exists();

        $hasMarketplace = DB::table('shops')
            ->where('organisation_id', $organisation->id)
            ->where('type', ShopTypeEnum::EXTERNAL->value)
            ->exists();

        $organisation->stats()->update([
            'has_fulfilment'   => $hasFulfilment,
            'has_dropshipping' => $hasDropshipping,
            'has_marketplace'  => $hasMarketplace,
        ]);
    }

    public function asCommand(Command $command): void
    {
        if ($command->option('slug')) {
            $organisation = Organisation::where('slug', $command->option('slug'))->first();

            if (!$organisation) {
                $command->error("Organisation not found.");
                return;
            }

            $command->info("Hydrating has_shops for organisation: {$organisation->slug}");
            $this->handle($organisation);
            $command->info("Done!");
        } else {
            $organisations = Organisation::where('type', OrganisationTypeEnum::SHOP)->get();

            if ($organisations->isEmpty()) {
                $command->warn("No shop organisations found.");
                return;
            }

            $command->info("Hydrating has_shops for all shop organisations...");

            $bar = $command->getOutput()->createProgressBar($organisations->count());
            $bar->setFormat('debug');
            $bar->start();

            foreach ($organisations as $organisation) {
                $this->handle($organisation);
                $bar->advance();
            }

            $bar->finish();
            $command->info("");
            $command->info("Completed hydrating all shop organisations!");
        }
    }
}
