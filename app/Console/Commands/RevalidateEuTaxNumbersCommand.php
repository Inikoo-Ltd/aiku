<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use App\Actions\Helpers\TaxNumber\ValidateEuropeanTaxNumber;
use App\Enums\Helpers\TaxNumber\TaxNumberStatusEnum;
use App\Enums\Helpers\TaxNumber\TaxNumberTypeEnum;
use App\Models\Helpers\TaxNumber;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;

/**
 * VIES faults used to be stamped as "invalid" on the number itself, so an unknown share of the
 * invalid EU VAT numbers are false negatives from a throttle or an outage, not from VIES saying no.
 * Nothing on the row tells the two apart, so the only way back is to ask VIES again.
 *
 * Sequential and paced on purpose: VIES throttles per member state (MS_MAX_CONCURRENT_REQ), and a
 * queue fan-out would earn exactly the faults this repairs.
 */
class RevalidateEuTaxNumbersCommand extends Command
{
    protected $signature = 'repair:eu-tax-numbers
                           {--country= : Only this country code}
                           {--limit=500 : How many numbers to re-check in this run}
                           {--sleep=1500 : Milliseconds between VIES calls}
                           {--dry-run : Show what would be re-checked without calling VIES}';

    protected $description = 'Re-check EU VAT numbers marked invalid, in case VIES was down when they were checked';

    public function handle(): int
    {
        Nightwatch::dontSample();

        $query = TaxNumber::where('type', TaxNumberTypeEnum::EU_VAT)
            ->where('status', TaxNumberStatusEnum::INVALID)
            ->when($this->option('country'), fn ($query, $country) => $query->where('country_code', strtoupper($country)))
            ->orderByDesc('invalid_checked_at');

        if ($this->option('dry-run')) {
            $this->line($query->count().' invalid EU VAT numbers match, '.$this->option('limit').' would be re-checked');

            return 0;
        }

        $action    = ValidateEuropeanTaxNumber::make();
        $recovered = 0;
        $faults    = 0;

        $taxNumbers = $query->limit((int)$this->option('limit'))->get();
        $bar        = $this->output->createProgressBar($taxNumbers->count());

        foreach ($taxNumbers as $taxNumber) {
            $action->handle($taxNumber, null, false);

            if ($taxNumber->valid) {
                $recovered++;
                $this->newLine();
                $this->line("recovered: {$taxNumber->id} {$taxNumber->country_code} {$taxNumber->number}");
            } elseif ($taxNumber->external_service_failed_at?->isAfter(now()->subMinutes(5))) {
                $faults++;
            }

            $bar->advance();
            usleep((int)$this->option('sleep') * 1000);
        }

        $bar->finish();
        $this->newLine(2);
        $this->line("checked {$taxNumbers->count()}, recovered {$recovered}, VIES faults {$faults}");

        return 0;
    }
}
