<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 10:30:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\CRM;

use App\Actions\Helpers\TaxNumber\UpdateTaxNumber;
use App\Enums\Helpers\TaxNumber\TaxNumberStatusEnum;
use App\Models\Helpers\TaxNumber;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Web registration used to take the tax number country from the browser's format guess, which
 * files any bare 11-digit number under Australia. This moves those never-validated tax numbers
 * to the customer's own country and validates them there.
 */
class RepairTaxNumbersGuessedAsAustralia
{
    use AsAction;

    public string $commandSignature = 'repair:tax_numbers_guessed_as_australia {--fix : Move them, otherwise only report}';

    public function query(): Builder
    {
        return TaxNumber::query()
            ->where('tax_numbers.owner_type', 'Customer')
            ->where('tax_numbers.country_code', 'AU')
            ->where('tax_numbers.status', TaxNumberStatusEnum::UNKNOWN)
            ->join('customers', 'customers.id', 'tax_numbers.owner_id')
            ->join('addresses', 'addresses.id', 'customers.address_id')
            ->where('addresses.country_code', '!=', 'AU')
            ->whereNotNull('addresses.country_id')
            ->select('tax_numbers.*', 'addresses.country_id as address_country_id', 'customers.slug as customer_slug')
            ->orderBy('tax_numbers.id');
    }

    public function handle(TaxNumber $taxNumber, int $countryId): TaxNumber
    {
        return UpdateTaxNumber::run($taxNumber, ['country_id' => $countryId]);
    }

    public function asCommand(Command $command): int
    {
        $fix = $command->option('fix');
        foreach ($this->query()->cursor() as $taxNumber) {
            $line = "{$taxNumber->customer_slug} {$taxNumber->number}";
            if ($fix) {
                $taxNumber = $this->handle($taxNumber, $taxNumber->address_country_id);
                $line      .= " -> {$taxNumber->country_code} {$taxNumber->status->value}";
            }
            $command->line($line);
        }

        return 0;
    }
}
