<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Sept 2026 20:45:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Everything needed to add a language, in the one order that is safe.
 *
 * The trap this exists to prevent: a new language must be pushed to POEditor before it
 * is ever pulled. translation:upload cannot seed an empty language (it calls
 * /translations/update, which answers `success` with `updated: 0`), and a t:down before
 * seeding overwrites the freshly generated file with POEditor's empty export.
 *
 * Activating the language is a database change, so it also writes a migration - a local
 * row update would never reach production.
 */
class TranslationsNew extends Command
{
    protected $signature = 't:new
                            {locale : ISO 639-1 code as it appears in the languages table, e.g. ko}
                            {--native= : Native name for the language picker, e.g. 한국어}
                            {--driver=gpt-4o-mini : Translation driver}
                            {--skip-translate : lang/<code>.json already exists}';

    protected $description = 'Add a language end to end: translate the UI, seed POEditor, activate it';

    public function handle(): int
    {
        $locale = $this->argument('locale');
        $language = DB::table('languages')->where('code', $locale)->first();

        if (!$language) {
            $this->error("No language with code '{$locale}' in the languages table.");

            return self::FAILURE;
        }

        $this->info("Adding {$language->name} ({$locale})");

        if (!$this->confirm("The languages table calls '{$locale}' {$language->name}. Correct?", true)) {
            $this->warn('Codes are ISO 639-1: Korean is ko, Nepali ne, Hindi hi. kr is Kanuri.');

            return self::SUCCESS;
        }

        if (!$this->option('skip-translate')) {
            $this->newLine();
            $this->info('→ translate:scan');
            $this->call('translate:scan', ['--lang' => 'en']);

            $this->newLine();
            $this->info('→ translate:default (slow; re-run with --skip-translate to fill gaps)');
            $this->call('translate:default', [
                'target_lang' => $locale,
                '--source_lang' => 'en',
                '--driver' => $this->option('driver'),
            ]);
        }

        $path = lang_path($locale.'.json');

        if (!is_file($path)) {
            $this->error("lang/{$locale}.json was not produced - nothing to seed.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('→ lang:strip-empty');
        $this->call('lang:strip-empty');

        $this->newLine();
        $this->info('→ lang:apply-glossary');
        $this->call('lang:apply-glossary', ['locale' => $locale]);

        $count = count(json_decode(file_get_contents($path), true));
        $this->newLine();
        $this->info("lang/{$locale}.json holds {$count} strings.");

        if ($this->confirm("Have you added '{$locale}' as a language in the POEditor project?", false)) {
            $this->newLine();
            $this->info('→ t:seed');

            if ($this->call('t:seed', ['locale' => $locale]) !== self::SUCCESS) {
                return self::FAILURE;
            }
        } else {
            $this->warn("Add it in POEditor, then run: php artisan t:seed {$locale}");
            $this->warn('Do NOT run t:down before seeding - it would overwrite the file with an empty export.');
        }

        $this->activate($locale, $language);

        return self::SUCCESS;
    }

    private function activate(string $locale, object $language): void
    {
        $native = $this->option('native') ?: $language->native_name;

        DB::table('languages')->where('code', $locale)->update([
            'status' => '1',
            'native_name' => $native,
        ]);

        $file = database_path('migrations/'.now()->format('Y_m_d_His')."_activate_{$locale}_language.php");

        file_put_contents($file, <<<PHP
        <?php

        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Support\\Facades\\DB;

        return new class () extends Migration {
            public function up(): void
            {
                DB::table('languages')->where('code', '{$locale}')->update(['status' => '1', 'native_name' => '{$native}']);
            }

            public function down(): void
            {
                DB::table('languages')->where('code', '{$locale}')->update(['status' => '0', 'native_name' => '{$language->native_name}']);
            }
        };

        PHP);

        $this->newLine();
        $this->info("Activated locally and wrote ".str_replace(base_path().'/', '', $file));

        if (!$this->option('native')) {
            $this->warn("native_name is '{$native}' - every other active locale uses its own script (Español, 简体中文). Re-run with --native to fix.");
        }
    }
}
