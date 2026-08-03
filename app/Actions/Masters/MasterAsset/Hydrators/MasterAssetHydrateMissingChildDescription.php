<?php

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;
use Illuminate\Console\Command;
use Illuminate\Contracts\Broadcasting\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateMissingChildDescription implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(MasterAsset $masterAsset): string
    {
        return (string) $masterAsset->id;
    }

    public function handle(MasterAsset $masterAsset): void
    {
        $hasMissingChildDescription = $masterAsset->products()
            ->where(function ($query) {
                $query->whereNull('products.description')->orWhere('products.description', '');
            })
            ->exists();

        $masterAsset->updateQuietly([
            'has_missing_child_description' => $hasMissingChildDescription,
        ]);
    }

    public string $commandSignature = 'hydrate:master_assets_missing_child_description {master_asset?}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('master_asset')) {
            $masterAsset = MasterAsset::where('slug', $command->argument('master_asset'))->firstOrFail();
            $this->handle($masterAsset);

            return;
        }

        $total = MasterAsset::where('type', MasterAssetTypeEnum::PRODUCT)->count();
        $bar   = $command->getOutput()->createProgressBar($total);
        $bar->setFormat('debug');
        $bar->start();

        MasterAsset::where('type', MasterAssetTypeEnum::PRODUCT)
            ->orderBy('id')
            ->chunkById(1000, function ($masterAssets) use ($bar) {
                foreach ($masterAssets as $masterAsset) {
                    $this->handle($masterAsset);
                    $bar->advance();
                }
            });

        $bar->finish();
        $command->newLine();
    }
}
