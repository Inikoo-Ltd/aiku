<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Traits\WithOrganisationSource;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use App\Models\SysAdmin\Organisation;
use App\Transfers\Aurora\WithAuroraParsers;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Rebuilds `master_assets.tax_category` from Aurora, the system being replaced, which is where
 * the zero rating of tea is actually recorded.
 *
 * Matches on `products.source_id` ("organisation id:aurora product id") rather than on the
 * product code. Codes are not unique - most tea has a plain master and a "(zero VAT)" twin
 * sharing one code - so the earlier import, which did `where code = ? -> first()`, dropped the
 * map on whichever it happened to read first. That is why ArTeaP-09 is zero rated on the twin
 * AWD sells nothing from, while the master UK and ES actually sell is still charged 20%.
 *
 * Aurora holds one product row per shop, so several rows can point at one Aiku master; their
 * maps are merged. Existing entries are kept, never dropped: a master that Aurora no longer
 * mentions is reported rather than cleared, because removing a zero rating is a tax decision.
 */
class RepairMasterAssetTaxCategoriesFromAurora
{
    use AsAction;
    use WithOrganisationSource;
    use WithAuroraParsers;

    /** @var array<int, int> masters reached by code rather than by source id, reported separately */
    private array $codeMatched = [];

    /** @var array<int, string> every product code Aurora gives a tax map, lower cased */
    private array $auroraCodes = [];

    public function getCommandSignature(): string
    {
        return 'maintenance:repair_master_tax_categories_from_aurora {--apply : write the changes, otherwise only report}';
    }

    /**
     * @return array<int, array<int, int>> master asset id => tax category map
     */
    public function handle(?Command $command = null): array
    {
        $mapsPerMaster = [];

        foreach (Organisation::whereNotNull('source')->get() as $organisation) {
            if (Arr::get($organisation->source, 'type') != 'Aurora') {
                continue;
            }

            $organisationSource = $this->getOrganisationSource($organisation);
            $organisationSource->initialisation($organisation);

            $auroraProducts = DB::connection('aurora')
                ->table('Product Dimension')
                ->select(['Product ID', 'Product Code', 'Product Tax Category Data'])
                ->whereNotNull('Product Tax Category Data')
                ->whereNotIn('Product Tax Category Data', ['{}', ''])
                ->get();

            $bySource = 0;
            $byCode   = 0;
            foreach ($auroraProducts as $auroraProduct) {
                $this->auroraCodes[] = strtolower($auroraProduct->{'Product Code'});

                $masterAssetIds = Product::where('source_id', $organisation->id.':'.$auroraProduct->{'Product ID'})
                    ->pluck('master_product_id')
                    ->filter()
                    ->unique()
                    ->all();

                if ($masterAssetIds) {
                    $bySource++;
                } else {
                    /*
                     * The ArtTT range reaches Aiku from the Slovak and Spanish databases, which
                     * carry no tax map, while its UK zero rating only exists in dw - so nothing
                     * points at the row holding the map. Falling back to the code covers that,
                     * and every master sharing the code is updated rather than an arbitrary one:
                     * a code is duplicated 9,383 times group wide, always as twins of the same
                     * product, and picking one is how the zero rating ended up on the twin
                     * nobody sells.
                     */
                    $masterAssetIds = MasterAsset::whereRaw('LOWER(code) = ?', [strtolower($auroraProduct->{'Product Code'})])
                        ->pluck('id')
                        ->all();

                    if ($masterAssetIds) {
                        $byCode++;
                        $this->codeMatched = array_unique(array_merge($this->codeMatched, $masterAssetIds));
                    }
                }

                foreach ($masterAssetIds as $masterAssetId) {
                    foreach (json_decode($auroraProduct->{'Product Tax Category Data'}, true) ?? [] as $auroraKey => $auroraValue) {
                        $mapsPerMaster[$masterAssetId][$this->parseTaxCategory($auroraKey)->id] = $this->parseTaxCategory($auroraValue)->id;
                    }
                }
            }

            $command?->line(sprintf(
                '%-8s %5d aurora products with a tax map, %5d matched by source id, %5d by code',
                $organisation->slug,
                $auroraProducts->count(),
                $bySource,
                $byCode
            ));
        }

        return $mapsPerMaster;
    }

    public function asCommand(Command $command): int
    {
        $mapsPerMaster = $this->handle($command);
        $apply         = (bool)$command->option('apply');

        $changed = [];
        foreach ($mapsPerMaster as $masterAssetId => $auroraMap) {
            /** @var MasterAsset $masterAsset */
            $masterAsset = MasterAsset::find($masterAssetId);
            if (!$masterAsset) {
                continue;
            }

            $merged = ($masterAsset->tax_category ?? []) + $auroraMap;
            ksort($merged);

            $current = $masterAsset->tax_category ?? [];
            ksort($current);

            if ($merged == $current) {
                continue;
            }

            $changed[] = [$masterAsset, $current, $merged];
        }

        $command->newLine();
        $command->line(count($changed).' master products need their tax treatment changed');

        foreach ($changed as [$masterAsset, $current, $merged]) {
            $command->line(sprintf(
                '  %-14s %-46s %s -> %s',
                $masterAsset->code,
                mb_strimwidth($masterAsset->name, 0, 45, '…'),
                json_encode($current),
                json_encode($merged)
            ).(in_array($masterAsset->id, $this->codeMatched) ? '   [matched by code]' : ''));

            if ($apply) {
                UpdateMasterAsset::make()->action($masterAsset, ['tax_category' => $merged]);
            }
        }

        /*
         * A map Aurora does not justify is left alone, but has to be seen: it zero rates.
         * A twin that Aurora reached through its sibling's source id counts as justified,
         * otherwise every "50g …" pair reports itself as unconfirmed while holding the
         * very value this command would have written.
         */
        $stray = MasterAsset::whereRaw("tax_category::text not in ('{}', '[]', 'null')")
            ->whereNotIn('id', array_keys($mapsPerMaster))
            ->whereNotIn(DB::raw('lower(code)'), array_unique($this->auroraCodes))
            ->get();

        if ($stray->isNotEmpty()) {
            $command->newLine();
            $command->warn($stray->count().' master products carry a tax map Aurora does not confirm, left untouched:');
            foreach ($stray as $masterAsset) {
                $command->line(sprintf('  %-14s %-46s %s', $masterAsset->code, mb_strimwidth($masterAsset->name, 0, 45, '…'), json_encode($masterAsset->tax_category)));
            }
        }

        if (!$apply) {
            $command->newLine();
            $command->info('Nothing written. Re-run with --apply to save.');
        }

        return 0;
    }
}
