<?php

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateBundles implements ShouldBeUnique
{
    use AsAction;
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:shop-bundles {organisations?*} {--s|slug=}';

    public function __construct()
    {
        $this->model = Shop::class;
    }

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function handle(Shop $shop): void
    {
        $numberBundles = 0;
        $numberBundlesStateActive = 0;
        $numberBundlesStateDiscontinuing = 0;

        Product::where('shop_id', $shop->id)->where('is_bundle', true)
            ->select('id', 'state')
            ->chunk(1000, function ($products) use (&$numberBundles, &$numberBundlesStateActive, &$numberBundlesStateDiscontinuing) {
                $numberBundles += $products->count();
                $numberBundlesStateActive += $products->where('state', ProductStateEnum::ACTIVE)->count();
                $numberBundlesStateDiscontinuing += $products->where('state', ProductStateEnum::DISCONTINUING)->count();
            });

        $shop->stats()->update([
            'number_bundles'                    => $numberBundles,
            'number_bundles_state_active'       => $numberBundlesStateActive,
            'number_bundles_state_discontinuing' => $numberBundlesStateDiscontinuing,
        ]);
    }
}
