<?php
/** @noinspection PhpUnused */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 19 Jul 2026 23:02:03 Malaysia Time, Plane Kula Lumpur - Bali
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Goods;

use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;
use App\Actions\Traits\WithOrganisationSource;

class GetTradeUnitsCountryOriginFromAurora
{
    use AsAction;
    use WithOrganisationSource;

    /**
     * @var \App\Transfers\AuroraOrganisationService|\App\Transfers\WowsbarOrganisationService|null
     */
    private \App\Transfers\WowsbarOrganisationService|null|AuroraOrganisationService $organisationSource;

    /**
     * @throws \Exception
     */
    public function handle(TradeUnit $tradeUnit, Command $command, bool $dryRun = false): void
    {
        $sources = $tradeUnit->sources;
        if (empty($sources)) {
            return;
        }

        $countryOrigins = [];

        foreach (Arr::get($sources, 'parts') as $source) {
            $sourceData               = explode(':', $source);
            $organisation             = Organisation::where('id', $sourceData[0])->first();
            $this->organisationSource = $this->getOrganisationSource($organisation);
            $this->organisationSource->initialisation($organisation);

            $auroraData = DB::connection('aurora')->table('Part Dimension')->select('Part Origin Country Code')->where('Part SKU', $sourceData[1])->first();


            $countryOrigins[$organisation->id] = $auroraData->{'Part Origin Country Code'};
        }


        $cleanCountryOrigins = array_unique(array_filter($countryOrigins, function ($value) {
            return !empty($value);
        }));


        if (count($cleanCountryOrigins) > 1) {
            print_r($countryOrigins);
            $command->info('Multiple country origins found for trade unit.');

            return;
        } elseif (empty($cleanCountryOrigins)) {
            // $command->info("$tradeUnit->slug No country origins found for trade unit.");

            $cleanCountryOrigins = $this->getOriginFromAuroraProduct($tradeUnit);
        }


        if (empty($cleanCountryOrigins)) {
            return;
        }

        $countryCode = reset($cleanCountryOrigins);
        $country     = Country::where('iso3', $countryCode)->first();

        if (!$country) {
            $command->error("Country not found for code: $countryCode");

            return;
        }


        if ($tradeUnit->origin_country_id !== $country->id) {
            $command->line("$tradeUnit->slug updating country of origin  ".$tradeUnit->countryOrigin?->iso3."  ($tradeUnit->country_of_origin)  ->  $countryCode");
            if (!$dryRun) {
                UpdateTradeUnit::make()->action(
                    $tradeUnit,
                    [
                        'origin_country_id' => $country->id,
                    ]
                );
            } else {
                $command->info("[DRY RUN] Would update $tradeUnit->slug origin country to $countryCode");
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function getOriginFromAuroraProduct(TradeUnit $tradeUnit): array
    {
        foreach ($tradeUnit->products as $product) {
            if ($product->source_id) {
                $sourceData               = explode(':', $product->source_id);
                $organisation             = Organisation::where('id', $sourceData[0])->first();
                $this->organisationSource = $this->getOrganisationSource($organisation);
                $this->organisationSource->initialisation($organisation);

                $auroraData = DB::connection('aurora')->table('Product Dimension')->select('Product Origin Country Code')->where('Product Id', $sourceData[1])->first();

                if ($auroraData->{'Product Origin Country Code'}) {
                    $countryOrigins[] = $auroraData->{'Product Origin Country Code'};

                    return $countryOrigins;
                }
            }
        }

        return [];
    }


    public function getCommandSignature(): string
    {
        return 'trade_units:get_country_origin {trade_unit?}  {--dry-run : Run without making actual changes}';
    }

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): int
    {
        if ($command->argument('trade_unit')) {
            $tradeUnit = TradeUnit::where('slug', $command->argument('trade_unit'))->firstOrFail();
            $this->handle($tradeUnit, $command, $command->option('dry-run'));

            return 0;
        }

        $total = DB::table('trade_units')->count();


        $command->info("Set country origin for $total trade units...");
        $start     = microtime(true);
        $processed = 0;

        $bar = new ProgressBar($command->getOutput(), $total);
        $bar->setFormat('debug');
        $bar->start();

        $query = DB::table('trade_units')
            ->select('id');


        $query
            ->orderBy('id')
            ->chunkById(1000, function ($tradeUnitRows) use (&$processed, $bar, $command) {
                foreach ($tradeUnitRows as $row) {
                    $tradeUnit = TradeUnit::find($row->id);
                    if ($tradeUnit) {
                        $this->handle($tradeUnit, $command, $command->option('dry-run'));
                    }
                    $processed++;
                    $bar->advance();
                }
            }, 'id');

        $bar->finish();
        $command->newLine(2);
        $duration = microtime(true) - $start;
        $command->info("Done. Processed $processed/$total trade units in ".gmdate('H:i:s', (int)$duration).".");

        return 0;
    }

}
