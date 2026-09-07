<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Sept 2026 21:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Machine-translates whatever is still missing from one or more locales.
 *
 * Safe to re-run: translate:default skips keys the locale already has, so each pass
 * only fills gaps. Worth running more than once - a batch that fails is skipped rather
 * than aborting the run, so a single pass rarely completes a locale.
 *
 * The result is local only. Push it with t:up, or t:new for a language POEditor still
 * has at 0%.
 */
class TranslationsFill extends Command
{
    protected $signature = 't:fill
                            {locales* : Language codes to fill, e.g. hi ne}
                            {--driver=gpt-4o-mini : Translation driver}';

    protected $description = 'Machine-translate the strings missing from a locale, then strip empties and apply the glossary';

    public function handle(): int
    {
        $locales = $this->argument('locales');

        $this->info('→ translate:scan');
        $this->call('translate:scan', ['--lang' => 'en']);

        $source = count(json_decode(file_get_contents(lang_path('en.json')), true));
        $rows = [];

        foreach ($locales as $locale) {
            $before = $this->countStrings($locale);

            $this->newLine();
            $this->info("→ translate:default {$locale} (slow, and quiet until it finishes)");

            $this->call('translate:default', [
                'target_lang' => $locale,
                '--source_lang' => 'en',
                '--driver' => $this->option('driver'),
            ]);

            $after = $this->countStrings($locale);
            $rows[] = [$locale, number_format($before), number_format($after), '+'.number_format($after - $before), number_format($source - $after)];
        }

        $this->newLine();
        $this->info('→ lang:strip-empty');
        $this->call('lang:strip-empty');

        $this->newLine();
        $this->info('→ lang:apply-glossary');
        $this->call('lang:apply-glossary');

        $this->newLine();
        $this->table(['locale', 'before', 'after', 'added', 'still missing'], $rows);

        if (collect($rows)->contains(fn (array $row) => (int) str_replace(',', '', $row[4]) > 0)) {
            $this->line('Run again to fill the rest - failed batches are skipped, not retried.');
        }

        $this->line('These translations are local only until t:up (or t:new for a language still at 0% in POEditor).');

        return self::SUCCESS;
    }

    private function countStrings(string $locale): int
    {
        $path = lang_path($locale.'.json');

        return is_file($path) ? count(json_decode(file_get_contents($path), true)) : 0;
    }
}
