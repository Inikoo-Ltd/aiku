<?php

namespace App\Actions\Catalogue\Shop\Seeders;

use App\Actions\GrpAction;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\SalesChannel;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedAddDefaultSalesChannelsToShop extends GrpAction
{
    use AsAction;

    public function handle(Shop $shop): void
    {
        $defaultSalesChannels = SalesChannel::where('is_seeded', true)->pluck('id');

        if ($defaultSalesChannels->isEmpty()) {
            return;
        }

        $shop->salesChannels()->syncWithoutDetaching($defaultSalesChannels);
    }

    public string $commandSignature = 'shop:add-default-sales-channels';
    public string $commandDescription = 'Add default (seeded) sales channels to all shops';

    public function asCommand(Command $command): int
    {
        $command->info("Adding default sales channels to all shops...");

        $shops = Shop::all();
        $bar = $command->getOutput()->createProgressBar($shops->count());
        $bar->start();

        foreach ($shops as $shop) {
            $this->handle($shop);
            $bar->advance();
        }

        $bar->finish();
        $command->newLine();
        $command->info("Done!");

        return 0;
    }
}
