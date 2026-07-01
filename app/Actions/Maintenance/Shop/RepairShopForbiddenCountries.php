<?php

namespace App\Actions\Maintenance\Shop;

use App\Actions\Catalogue\Shop\UpdateShop;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairShopForbiddenCountries
{
    use AsAction;

    public function handle(Shop $shop, Command $command)
    {
        if (empty($shop->forbidden_dispatch_countries)) {
            $command->info("Skipping Shop [$shop->slug]: Shop does not have any forbidden_dispatch_countries");
            return;
        }

        $countryList = Country::whereIn('id', $shop->forbidden_dispatch_countries)
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item['code'] => [
                    'postcode'  => null,
                    'billing'   => false,
                    'delivery'  => true
                ]
            ])->toArray();

        UpdateShop::make()->action($shop, [
            'banned_countries'  => $countryList
        ]);

        $command->info("Updated Shop [$shop->slug]");
    }

    public string $commandSignature = 'repair:shop_forbidden_countries {--shop_id=}';

    public function asCommand(Command $command)
    {
        $shopId = $command->option('shop_id');
        $shops  = Shop::when($shopId,
                fn ($q) => $q->where('shop_id', $shopId)
            )
            ->get();
        
        foreach ($shops as $shop) {
            $this->handle($shop, $command);
        }
    }
}
