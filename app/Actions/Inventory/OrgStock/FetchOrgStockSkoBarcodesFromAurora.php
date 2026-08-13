<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Aug 2026 12:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Traits\WithOrganisationSource;
use App\Actions\Transfers\Aurora\WithAuroraOrganisationsArgument;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Aurora's `Part Dimension`.`Part SKO Barcode` is the outer/SKO CODE 128 barcode printed on the
 * external packing, keyed by `Part SKU`. Physical labels are sometimes wrong or missing, so this
 * never throws, it only reports what it would do (or does, with --apply) per organisation.
 */
class FetchOrgStockSkoBarcodesFromAurora
{
    use AsAction;
    use WithOrganisationSource;
    use WithAuroraOrganisationsArgument;

    public string $commandSignature = 'org_stocks:fetch_sko_barcodes {organisations?*} {--apply}';

    protected array $junkValues = ['auto', 'new', 'bcdisp'];

    public function cleanBarcode(?string $value): ?string
    {
        $value = trim((string)$value);

        if (blank($value) || mb_strlen($value) < 5) {
            return null;
        }

        if (in_array(mb_strtolower($value), $this->junkValues, true)) {
            return null;
        }

        if (!preg_match('/[a-zA-Z0-9]/', $value)) {
            return null;
        }

        return $value;
    }

    public function handle(Organisation $organisation, bool $apply, Command $command): void
    {
        $organisationSource = $this->getOrganisationSource($organisation);
        $organisationSource->initialisation($organisation);

        $auroraBarcodes = DB::connection('aurora')
            ->table('Part Dimension')
            ->pluck('Part SKO Barcode', 'Part SKU')
            ->all();

        $wouldFill        = 0;
        $skippedDuplicate = 0;
        $skippedJunk      = 0;

        OrgStock::where('organisation_id', $organisation->id)
            ->where('independent_barcode', false)
            ->whereNull('barcode')
            ->whereNotNull('source_id')
            ->chunkById(1000, function ($orgStocks) use ($auroraBarcodes, $apply, $organisation, &$wouldFill, &$skippedDuplicate, &$skippedJunk) {
                foreach ($orgStocks as $orgStock) {
                    $sku = explode(':', $orgStock->source_id, 2)[1] ?? null;

                    if ($sku === null || !array_key_exists($sku, $auroraBarcodes)) {
                        continue;
                    }

                    $rawBarcode = trim((string)$auroraBarcodes[$sku]);

                    if ($rawBarcode === '') {
                        continue;
                    }

                    $barcode = $this->cleanBarcode($rawBarcode);

                    if ($barcode === null) {
                        $skippedJunk++;

                        continue;
                    }

                    if (OrgStock::where('organisation_id', $organisation->id)
                        ->where('id', '!=', $orgStock->id)
                        ->whereNull('deleted_at')
                        ->where('barcode', $barcode)
                        ->exists()) {
                        $skippedDuplicate++;

                        continue;
                    }

                    $wouldFill++;

                    if ($apply) {
                        $orgStock->update(['barcode' => $barcode]);
                    }
                }
            });

        $command->info(
            "$organisation->slug: ".($apply ? "$wouldFill filled" : "$wouldFill would be filled")
            .", $skippedDuplicate skipped as duplicate, $skippedJunk skipped as junk"
        );
    }

    public function asCommand(Command $command): int
    {
        $apply = (bool)$command->option('apply');

        foreach ($this->getOrganisations($command) as $organisation) {
            $this->handle($organisation, $apply, $command);
        }

        return 0;
    }
}
