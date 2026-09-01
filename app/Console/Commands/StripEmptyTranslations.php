<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Sept 2026 16:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Laravel resolves an empty translation to the English key ($line ?: $key), but
 * laravel-vue-i18n treats "" as a valid message and renders a blank label, so every
 * untranslated string in grp shows as nothing at all. Deleting the key restores the
 * English fallback on both sides. POEditor downloads reintroduce them, so this is
 * meant to run after `translation:download`.
 */
class StripEmptyTranslations extends Command
{
    protected $signature = 'lang:strip-empty {--dry-run : Report what would be removed without writing}';

    protected $description = 'Remove empty-valued keys from lang/*.json so untranslated strings fall back to English instead of rendering blank';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $rows = [];
        $total = 0;

        foreach (glob(lang_path('*.json')) as $path) {
            $locale = basename($path, '.json');

            if (in_array($locale, ['en', 'php_vendor', 'texts_to_translate'], true)) {
                continue;
            }

            $strings = json_decode(file_get_contents($path), true);

            if (!is_array($strings)) {
                $this->warn("Skipped {$locale}: not a JSON object");
                continue;
            }

            $kept = array_filter($strings, fn ($value) => is_string($value) && trim($value) !== '');
            $removed = count($strings) - count($kept);

            if ($removed === 0) {
                continue;
            }

            $total += $removed;
            $rows[] = [$locale, number_format($removed), number_format(count($kept))];

            if (!$dryRun) {
                file_put_contents(
                    $path,
                    json_encode($kept, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
                );
            }
        }

        $this->table(['locale', 'removed', 'kept'], $rows);
        $this->info(($dryRun ? 'Would remove ' : 'Removed ').number_format($total).' empty strings');

        return self::SUCCESS;
    }
}
