<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Sept 2026 20:30:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Seeds a language POEditor holds but has no translations for.
 *
 * translation:upload calls /translations/update, which cannot populate an empty
 * language: it answers `success` with `updated: 0` and leaves it at 0%. Only
 * /translations/add will do it, and the POEditor package never calls that endpoint.
 *
 * Run this straight after adding a language in POEditor and BEFORE any t:down —
 * pulling first overwrites the local file with an empty export.
 */
class TranslationsSeed extends Command
{
    private const BATCH = 800;

    // POEditor allows one upload per 20 seconds per project.
    private const PAUSE_SECONDS = 21;

    protected $signature = 't:seed {locale : Language code, must already exist in POEditor}';

    protected $description = 'Push a locale into POEditor for the first time, which translation:upload cannot do';

    public function handle(): int
    {
        $locale = $this->argument('locale');
        $path = lang_path($locale.'.json');

        if (!is_file($path)) {
            $this->error("lang/{$locale}.json does not exist - generate it first");

            return self::FAILURE;
        }

        $entries = collect(json_decode(file_get_contents($path), true))
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value, $term) => ['term' => $term, 'translation' => ['content' => $value]])
            ->values();

        if ($entries->isEmpty()) {
            $this->error("lang/{$locale}.json has no translations to send");

            return self::FAILURE;
        }

        $batches = $entries->chunk(self::BATCH);
        $this->info("Seeding {$locale}: {$entries->count()} translations in {$batches->count()} batches");

        $added = 0;

        foreach ($batches->values() as $index => $batch) {
            $response = Http::asForm()->timeout(120)->post('https://api.poeditor.com/v2/translations/add', [
                'api_token' => config('translation.api_key'),
                'id' => config('translation.project_id'),
                'language' => $locale,
                'data' => $batch->values()->toJson(),
            ]);

            $batchAdded = (int) $response->json('result.translations.added', 0);
            $added += $batchAdded;

            $this->line(sprintf('  batch %d/%d: added %d', $index + 1, $batches->count(), $batchAdded));

            if ($index + 1 < $batches->count()) {
                sleep(self::PAUSE_SECONDS);
            }
        }

        $this->info("{$locale}: {$added} translations added");
        $this->line('A shortfall is usually whitespace-variant keys POEditor normalised away.');

        return self::SUCCESS;
    }
}
