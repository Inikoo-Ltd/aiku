<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Sept 2026 16:30:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * A bare UI string carries no context, so machine translation reliably mangles the
 * short commerce terms: "Charge" became आरोप (a legal accusation) in Hindi and
 * "Close" became Cerca (nearby) in Spanish. resources/translation-glossary.json holds the corrections;
 * this reapplies them after any translate:default run or POEditor download.
 *
 * `translation:download` overwrites lang/<code>.json wholesale, so the corrections are
 * lost on every download until this runs again. Pair it with lang:strip-empty:
 *
 *     php artisan translation:download
 *     php artisan lang:strip-empty
 *     php artisan lang:apply-glossary
 */
class ApplyTranslationGlossary extends Command
{
    protected $signature = 'lang:apply-glossary {locale? : Only this locale} {--dry-run : Report without writing}';

    protected $description = 'Overwrite machine-translated UI terms with the curated values in resources/translation-glossary.json';

    public function handle(): int
    {
        $glossaryPath = resource_path('translation-glossary.json');

        if (!is_file($glossaryPath)) {
            $this->error('resources/translation-glossary.json not found');

            return self::FAILURE;
        }

        $glossary = json_decode(file_get_contents($glossaryPath), true);
        $only = $this->argument('locale');
        $rows = [];

        foreach ($glossary as $locale => $terms) {
            if (str_starts_with($locale, '_') || ($only && $locale !== $only)) {
                continue;
            }

            $path = lang_path($locale.'.json');

            if (!is_file($path)) {
                $this->warn("Skipped {$locale}: lang/{$locale}.json does not exist");
                continue;
            }

            $strings = json_decode(file_get_contents($path), true);
            $changed = 0;

            foreach ($terms as $key => $value) {
                if (($strings[$key] ?? null) !== $value) {
                    $strings[$key] = $value;
                    $changed++;
                }
            }

            $rows[] = [$locale, $changed, count($terms)];

            if ($changed > 0 && !$this->option('dry-run')) {
                ksort($strings);
                file_put_contents(
                    $path,
                    json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
                );
            }
        }

        $this->table(['locale', 'corrected', 'glossary terms'], $rows);

        return self::SUCCESS;
    }
}
