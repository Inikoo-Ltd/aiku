<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Sept 2026 20:20:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Pull translations from POEditor, which is the source of truth, and repair them.
 * Each step exists because the one before it breaks something:
 *
 *  1. translation:download - replaces every lang/<code>.json POEditor knows about,
 *                            wholesale. It also drops terms POEditor has not got yet.
 *  2. lang:strip-empty     - the export carries an empty string for every untranslated
 *                            term, and laravel-vue-i18n renders "" as a blank label.
 *  3. lang:apply-glossary  - restores the business terms machine translation mangles,
 *                            without touching anything a translator has edited.
 *
 * It does not scan the code: that belongs to t:up, which is when new terms matter. A
 * download drops terms POEditor has not got yet, so after t:down en.json holds what
 * POEditor holds. t:up rescans before pushing and puts them back.
 */
class TranslationsDown extends Command
{
    protected $signature = 't:down {--skip-download : Repair and rescan the local files without pulling}';

    protected $description = 'Pull translations from POEditor and repair them (download, strip empties, apply glossary, rescan)';

    public function handle(): int
    {
        $steps = $this->option('skip-download') ? [] : [['translation:download', []]];

        $steps[] = ['lang:strip-empty', []];
        $steps[] = ['lang:apply-glossary', []];

        foreach ($steps as [$command, $arguments]) {
            $this->newLine();
            $this->info("→ {$command}");

            if ($this->call($command, $arguments) !== self::SUCCESS) {
                $this->error("{$command} failed - stopping so the next step cannot make it worse");

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('Done. Review `git diff lang/` before committing.');
        $this->line('New strings in the code are not terms yet - t:up scans and pushes them.');

        return self::SUCCESS;
    }
}
