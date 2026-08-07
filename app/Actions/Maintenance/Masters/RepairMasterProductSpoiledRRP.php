<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Masters\MasterAsset\UpdateMasterAssetPrices;
use App\Actions\OrgAction;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Both price and rrp are stored per outer, so their ratio does not depend on units and a
 * healthy master sits around the house 2.4 (a ~58% margin). Masters that carried their pack
 * size in units=1 often had a per-unit figure typed into the rrp, which lands far below that
 * band and gets worse once units is corrected. There is no arithmetic that recovers the
 * intended rrp, so a spoiled one is rebuilt from its own price at the house ratio.
 */
class RepairMasterProductSpoiledRRP extends OrgAction
{
    use AsAction;

    public const float HOUSE_RATIO = 2.4;
    public const float MIN_RATIO   = 1.5;
    public const float MAX_RATIO   = 4.0;

    /**
     * @return array{code: string, currency: string, price: float, was: float, now: float}|null
     */
    public function handle(MasterAsset $masterAsset, float $ratio = self::HOUSE_RATIO, bool $fix = false): ?array
    {
        if ($masterAsset->type != MasterAssetTypeEnum::PRODUCT) {
            return null;
        }

        $baseCurrency = 'EUR';
        $price        = (float) data_get($masterAsset->master_prices, "$baseCurrency.value");
        $rrp          = (float) data_get($masterAsset->master_rrps, "$baseCurrency.value");

        if ($price <= 0 || $rrp <= 0) {
            return null;
        }

        $currentRatio = $rrp / $price;
        if ($currentRatio >= self::MIN_RATIO && $currentRatio <= self::MAX_RATIO) {
            return null;
        }

        $rrps = [];
        foreach ($masterAsset->master_prices as $currencyCode => $entry) {
            $currencyPrice = (float) ($entry['value'] ?? 0);
            if ($currencyPrice > 0) {
                $rrps[$currencyCode] = ['value' => round($currencyPrice * $ratio, 2)];
            }
        }

        if ($fix && $rrps) {
            UpdateMasterAssetPrices::make()->action($masterAsset, ['master_rrps' => $rrps]);
        }

        return [
            'code'     => $masterAsset->code,
            'currency' => $baseCurrency,
            'price'    => $price,
            'was'      => $rrp,
            'now'      => round($price * $ratio, 2),
        ];
    }

    public function getCommandSignature(): string
    {
        return 'repair:master_products_spoiled_rrp {--master-shop=aw} {--ratio=2.4} {--fix}';
    }

    public function asCommand(Command $command): int
    {
        $ratio = (float) $command->option('ratio');
        $fix   = (bool) $command->option('fix');

        $command->info(
            ($fix ? 'Rebuilding' : 'Listing').' rrp outside the '.self::MIN_RATIO.'–'.self::MAX_RATIO
            .' band, at ratio '.$ratio
        );

        $rows = [];
        MasterAsset::where('is_main', true)
            ->where('status', true)
            ->whereHas('masterShop', fn ($query) => $query->where('slug', $command->option('master-shop')))
            ->chunkById(100, function ($masterAssets) use (&$rows, $ratio, $fix) {
                foreach ($masterAssets as $masterAsset) {
                    if ($finding = $this->handle($masterAsset, $ratio, $fix)) {
                        $rows[] = $finding;
                    }
                }
            });

        $command->table(['code', 'currency', 'price', 'was', 'now'], array_slice($rows, 0, 30));
        $command->info(count($rows).' masters '.($fix ? 'rebuilt' : 'would be rebuilt'));

        return 0;
    }
}
