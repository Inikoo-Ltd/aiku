<?php

namespace App\Actions\Catalogue\Product;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Masters\MasterAsset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class DiscontinueProductFromMasterAsset implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(MasterAsset $masterAsset): string
    {
        return $masterAsset->id;
    }

    public function handle(MasterAsset $masterAsset)
    {
        foreach ($masterAsset->products()->whereNot('state', ProductStateEnum::DISCONTINUED)->get() as $product) {
            UpdateProduct::make()->action($product, [
                'state' => ProductStateEnum::DISCONTINUING
            ]);
        }
    }
}
