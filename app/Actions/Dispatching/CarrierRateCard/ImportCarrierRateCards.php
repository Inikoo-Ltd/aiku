<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\CarrierRateCard;

use App\Models\Dispatching\CarrierRateCard;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Loads carrier rate cards transcribed from the carriers' PDFs. The file is kept outside the repo (the PDFs are confidential).
 * It holds named areas, each a list of {country, postcodes} rules, and cards whose zones list the areas they cover,
 * so the same postcode rules are written once. Importing a card again with the same carrier, service and valid_from replaces it;
 * next year's PDFs become new cards with their own dates and last year's stay as they were.
 */
class ImportCarrierRateCards
{
    use AsAction;

    public string $commandSignature = 'carrier-rate-cards:import {organisation : organisation slug} {file : path to the rate cards JSON}';

    /**
     * @param array{areas: array<string, array<int, array{country: string, postcodes?: array<int, string>}>>, cards: array<int, array<string, mixed>>} $data
     */
    public function handle(Organisation $organisation, array $data): int
    {
        $areas = $data['areas'] ?? [];

        return DB::transaction(function () use ($organisation, $data, $areas) {
            foreach ($data['cards'] as $card) {
                $zones = collect($card['zones'])->map(function (array $zone, string $name) use ($areas, $card) {
                    $match = collect($zone['covers'])->flatMap(function (string $area) use ($areas, $card) {
                        if (!isset($areas[$area]) && !preg_match('/^[A-Z]{2}$/', $area)) {
                            throw ValidationException::withMessages(['file' => "{$card['carrier']} {$card['service']}: unknown area $area"]);
                        }

                        return $areas[$area] ?? [['country' => $area]];
                    })->values()->all();

                    return array_filter([
                        'name'                 => $name,
                        'match'                => $match,
                        'bands'                => $zone['bands'] ?? [],
                        'extra_kg'             => $zone['extra_kg'] ?? null,
                        'volumetric_kg_per_m3' => $zone['volumetric_kg_per_m3'] ?? null,
                        'max_kg'               => $zone['max_kg'] ?? null,
                    ], fn ($value, string $key) => $value !== null || $key === 'extra_kg', ARRAY_FILTER_USE_BOTH);
                })->values()->all();

                CarrierRateCard::updateOrCreate(
                    ['organisation_id' => $organisation->id, 'carrier' => $card['carrier'], 'service' => $card['service'], 'valid_from' => $card['valid_from']],
                    [
                        'group_id'             => $organisation->group_id,
                        'valid_to'             => $card['valid_to'] ?? null,
                        'volumetric_kg_per_m3' => $card['volumetric_kg_per_m3'],
                        'max_kg'               => $card['max_kg'],
                        'surcharge_percent'    => $card['surcharge_percent'] ?? 0,
                        'zones'                => $zones,
                        'notes'                => $card['notes'] ?? null,
                    ]
                );
            }

            return count($data['cards']);
        });
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $data         = json_decode(file_get_contents($command->argument('file')), true, flags: JSON_THROW_ON_ERROR);

        $command->info($this->handle($organisation, $data).' rate cards loaded for '.$organisation->code);

        return Command::SUCCESS;
    }
}
