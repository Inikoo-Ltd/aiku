<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Sept 2026 21:15:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Records a correction for a UI term and applies it.
 *
 * Machine translation mangles short context-free strings the same way in every language:
 * "Close" became Cerca (nearby) in Spanish, "Charge" became आरोप (a legal accusation) in
 * Hindi. Editing lang/<code>.json alone is not enough - the next POEditor download
 * overwrites it. Writing the term to the glossary makes the fix survive, because
 * lang:apply-glossary reapplies it after every download.
 *
 * Use this whenever someone reports a bad label. One report, one command, fixed forever.
 */
class TranslationsFix extends Command
{
    protected $signature = 't:fix
                            {locale : Language code, e.g. es}
                            {term? : The English string, e.g. "Close"}
                            {translation? : What it should say, e.g. "Cerrar"}';

    protected $description = 'Correct a mistranslated UI term so it survives future POEditor downloads';

    public function handle(): int
    {
        $locale = $this->argument('locale');
        $term = $this->argument('term');
        $translation = $this->argument('translation');

        $glossaryPath = resource_path('translation-glossary.json');
        $glossary = json_decode(file_get_contents($glossaryPath), true);

        if (!$term) {
            return $this->listTerms($glossary, $locale);
        }

        $langPath = lang_path($locale.'.json');

        if (!is_file($langPath)) {
            $this->error("lang/{$locale}.json does not exist");

            return self::FAILURE;
        }

        $english = json_decode(file_get_contents(lang_path('en.json')), true);

        if (!array_key_exists($term, $english)) {
            $this->warn("'{$term}' is not a term in en.json - check the exact string, including capitals and trailing spaces.");

            if (!$this->confirm('Record it anyway?', false)) {
                return self::SUCCESS;
            }
        }

        $strings = json_decode(file_get_contents($langPath), true);
        $was = $strings[$term] ?? null;

        $glossary[$locale][$term] = $translation;
        ksort($glossary[$locale]);
        file_put_contents($glossaryPath, json_encode($glossary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        $strings[$term] = $translation;
        ksort($strings);
        file_put_contents($langPath, json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        $this->info(sprintf('%s: "%s" is now "%s"%s', $locale, $term, $translation, $was === null ? '' : ' (was "'.$was.'")'));
        $this->line('Recorded in the glossary, so a POEditor download cannot undo it.');
        $this->line('Run t:up to push the correction to POEditor.');

        return self::SUCCESS;
    }

    private function listTerms(array $glossary, string $locale): int
    {
        $terms = $glossary[$locale] ?? [];

        if (!$terms) {
            $this->info("No corrections recorded for {$locale} yet.");

            return self::SUCCESS;
        }

        $this->table(
            ['term', 'correction'],
            collect($terms)->map(fn ($value, $term) => [$term, $value])->values()->all()
        );

        return self::SUCCESS;
    }
}
