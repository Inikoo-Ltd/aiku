<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Sep 2026 15:30:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\Production;
use App\Models\Production\RecipeStepRawMaterial;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;

class NormaliseAuroraRecipeQuantities extends Command
{
    protected $signature = 'manufacture:normalise-aurora-recipes
                           {production : Production slug}
                           {--write : Persist changes; without it the command only reports}';

    protected $description = 'Convert Aurora part-list recipe quantities (given per batch) into per-unit quantities by dividing by the recommended batch size';

    public function handle(): int
    {
        Nightwatch::dontSample();

        $production = Production::where('slug', $this->argument('production'))->first();
        if (!$production) {
            $this->error("Unknown production '{$this->argument('production')}'.");

            return Command::FAILURE;
        }
        $write = (bool) $this->option('write');

        $artefacts = Artefact::where('production_id', $production->id)
            ->whereNotNull('source_id')
            ->where('source_id', 'not like', 'costings:%')
            ->where('recommended_batch_size', '>', 1)
            ->whereNull('data->recipe_quantities_normalised_at')
            ->get();

        $normalised = 0;
        $lines      = 0;
        foreach ($artefacts as $artefact) {
            $stepIds = ArtefactManufactureTask::where('artefact_id', $artefact->id)->pluck('id');
            $recipe  = RecipeStepRawMaterial::whereIn('artefact_manufacture_task_id', $stepIds)->get();
            if ($recipe->isEmpty()) {
                continue;
            }
            $normalised++;
            $lines += $recipe->count();
            $this->line(sprintf('%s batch %d: %d lines', $artefact->code, $artefact->recommended_batch_size, $recipe->count()));
            if (!$write) {
                continue;
            }
            foreach ($recipe as $line) {
                $line->update(['quantity_per_unit' => round($line->quantity_per_unit / $artefact->recommended_batch_size, 6)]);
            }
            $artefact->update(['data' => array_merge($artefact->data ?? [], ['recipe_quantities_normalised_at' => now()->toIso8601String()])]);
        }

        $this->info(($write ? 'Normalised ' : 'Would normalise ')."$normalised artefacts, $lines lines.");

        return Command::SUCCESS;
    }
}
