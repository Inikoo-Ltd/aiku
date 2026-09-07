<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Sept 2026 21:30:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Finds broken translations without asking a model anything, so it costs nothing and
 * can run on every locale as often as you like.
 *
 * It cannot judge meaning - only a reader can tell you "Cerca" is wrong for Close. What
 * it does catch is the mechanical damage machine translation causes, which is most of it:
 * lost placeholders, mangled markup, two different terms collapsing onto one word,
 * answers left in English, and values so long the model explained instead of translating.
 */
class TranslationsCheck extends Command
{
    /** Locales whose translations should not be mostly Latin characters. */
    private const SCRIPTS = [
        'hi' => '\p{Devanagari}',
        'ne' => '\p{Devanagari}',
        'zh-Hans' => '\p{Han}',
        'ja' => '\p{Han}\p{Hiragana}\p{Katakana}',
        'uk' => '\p{Cyrillic}',
        'bg' => '\p{Cyrillic}',
    ];

    protected $signature = 't:check {locales?* : Locales to check, default all} {--sample=3 : Examples to show per issue}';

    protected $description = 'Report broken translations - lost placeholders, mangled markup, collisions, untranslated values';

    public function handle(): int
    {
        $english = json_decode(file_get_contents(lang_path('en.json')), true);
        $locales = $this->argument('locales') ?: $this->allLocales();
        $sample = (int) $this->option('sample');
        $rows = [];

        foreach ($locales as $locale) {
            $path = lang_path($locale.'.json');

            if (!is_file($path)) {
                $this->warn("skipped {$locale}: no lang/{$locale}.json");
                continue;
            }

            $strings = json_decode(file_get_contents($path), true);
            $issues = $this->inspect($locale, $strings, $english);

            $rows[] = [
                $locale,
                number_format(count($strings)),
                count($issues['placeholders']),
                count($issues['markup']),
                count($issues['collisions']),
                count($issues['english']),
                count($issues['runaway']),
            ];

            foreach ($issues as $kind => $found) {
                foreach (array_slice($found, 0, $sample) as $line) {
                    $this->line("  <comment>{$locale} {$kind}</comment>  {$line}");
                }
            }
        }

        $this->newLine();
        $this->table(['locale', 'strings', 'placeholders', 'markup', 'collisions', 'still English', 'runaway'], $rows);
        $this->line('Fix one with: php artisan t:fix <locale> "<term>" "<correction>"');

        return self::SUCCESS;
    }

    private function inspect(string $locale, array $strings, array $english): array
    {
        $issues = ['placeholders' => [], 'markup' => [], 'collisions' => [], 'english' => [], 'runaway' => []];
        $seen = [];

        foreach ($strings as $term => $value) {
            if (!is_string($value) || trim($value) === '' || !isset($english[$term])) {
                continue;
            }

            $source = $english[$term];

            if ($this->tokens('/:\w+|\{\w+\}|%[sd]/', $source) !== $this->tokens('/:\w+|\{\w+\}|%[sd]/', $value)) {
                $issues['placeholders'][] = "\"{$term}\" -> \"{$value}\"";
            }

            if ($this->tokens('/<[^>]+>/', $source) !== $this->tokens('/<[^>]+>/', $value) || str_contains($value, '\\/')) {
                $issues['markup'][] = "\"{$term}\" -> \"{$value}\"";
            }

            // Two different English terms landing on one word is how Dispatch and
            // Submitted both became "Enviado" in Spanish.
            if (isset($seen[$value]) && mb_strlen($value) < 30) {
                $issues['collisions'][] = "\"{$seen[$value]}\" and \"{$term}\" are both \"{$value}\"";
            }
            $seen[$value] = $term;

            if ($this->looksUntranslated($locale, $term, $value, $source)) {
                $issues['english'][] = "\"{$term}\" -> \"{$value}\"";
            }

            if (mb_strlen($source) > 3 && mb_strlen($value) > mb_strlen($source) * 3.5) {
                $issues['runaway'][] = "\"{$term}\" -> \"{$value}\"";
            }
        }

        return $issues;
    }

    private function looksUntranslated(string $locale, string $term, string $value, string $source): bool
    {
        if (!isset(self::SCRIPTS[$locale])) {
            // Latin-script locales legitimately share words with English, so only flag
            // longer strings that came back byte for byte.
            return $value === $source && str_word_count($source) > 3;
        }

        return !preg_match('/['.self::SCRIPTS[$locale].']/u', $value) && preg_match('/\p{L}/u', $value);
    }

    private function tokens(string $pattern, string $subject): array
    {
        preg_match_all($pattern, $subject, $matches);
        $found = $matches[0];
        sort($found);

        return $found;
    }

    private function allLocales(): array
    {
        return collect(glob(lang_path('*.json')))
            ->map(fn ($path) => basename($path, '.json'))
            ->reject(fn ($locale) => in_array($locale, ['en', 'php_vendor', 'texts_to_translate'], true))
            ->values()
            ->all();
    }
}
