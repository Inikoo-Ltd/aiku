<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Seeders;

use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedShopShortNames
{
    use AsAction;

    public string $commandSignature = 'shops:seed_short_names {--N|dry_run}';
    public string $commandDescription = 'Give shops without a short name the curated one, keyed by shop code';

    /**
     * @var array<string, string>
     */
    public const array SHORT_NAMES = [
        'ACAR'  => 'A&C',
        'ACFE'  => 'A&C Faire',
        'ARFE'  => 'Aromatics Faire',
        'AROMA' => 'Aromatics',
        'EZC'   => 'Eazycolours',

        'AWD'  => 'Dropship UK',
        'AWF'  => 'Fulfilment',
        'AWFE' => 'Faire UK',
        'HATI' => 'HatiNest',
        'UK'   => 'AW UK',

        'ACES' => 'A&C ES',
        'ADE'  => 'Deutschland',
        'AEU'  => 'Europe',
        'AFR'  => 'France',
        'DSE'  => 'Dropship ES',
        'ES'   => 'España',
        'ESFE' => 'Faire',
        'PT'   => 'Portugal',

        'ACEU' => 'A&C EU',
        'AT'   => 'Österreich',
        'BG'   => 'България',
        'CZ'   => 'Česko',
        'DSSK' => 'Dropship EU',
        'EU'   => 'Europe',
        'EUF'  => 'Fulfilment',
        'FREU' => 'France',
        'HR'   => 'Hrvatska',
        'HU'   => 'Magyarország',
        'ITEU' => 'Italia',
        'NL'   => 'Nederland',
        'PLSK' => 'Polska',
        'RO'   => 'România',
        'SE'   => 'Sverige',
        'SK'   => 'Slovensko',
        'SKFE' => 'Faire EU',
        'UA'   => 'Україна',
    ];

    /**
     * @return array<int, string>
     */
    public function handle(bool $dryRun = false): array
    {
        $changes = [];

        Shop::whereNull('short_name')->each(function (Shop $shop) use ($dryRun, &$changes) {
            $shortName = self::SHORT_NAMES[strtoupper($shop->code)] ?? null;
            if (!$shortName) {
                return;
            }

            $changes[] = "$shop->code: $shop->name -> $shortName";
            if (!$dryRun) {
                $shop->update(['short_name' => $shortName]);
            }
        });

        return $changes;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $changes = $this->handle($command->option('dry_run'));
        foreach ($changes as $line) {
            $command->line($line);
        }
        $command->info(($command->option('dry_run') ? 'Would set ' : 'Set ').count($changes));

        return 0;
    }
}
