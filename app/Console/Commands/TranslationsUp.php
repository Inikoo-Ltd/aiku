<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Sept 2026 20:20:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Push the term list and translations to POEditor.
 *
 * Runs t:down first, always. translation:upload calls /translations/update, which
 * OVERWRITES: pushing a stale local file silently destroys whatever a translator fixed
 * in POEditor. Pulling first means the local files already carry their edits, so the
 * upload cannot undo anything. The scan then runs with --merge, so new strings in the
 * code become terms and anything the download dropped comes back; a plain scan would
 * delete whatever it cannot see, such as bare template text.
 *
 * One thing this still cannot do: /translations/update will not seed a language that has
 * no translations at all. A brand-new language needs /translations/add, which the package
 * never calls, so the upload reports success and leaves it at 0%.
 */
class TranslationsUp extends Command
{
    protected $signature = 't:up {--skip-download : Push without pulling first, only when nobody has touched POEditor}';

    protected $description = 'Pull, repair, then push terms and translations to POEditor';

    public function handle(): int
    {
        if (!$this->option('skip-download')) {
            $this->info('→ t:down (pulling first so the upload cannot overwrite anyone)');

            if ($this->call('t:down') !== self::SUCCESS) {
                $this->error('t:down failed - not uploading a half-repaired set of files');

                return self::FAILURE;
            }
        }

        foreach ([['translation:scan', ['--merge' => true]], ['translation:upload', []]] as [$command, $arguments]) {
            $this->newLine();
            $this->info("→ {$command}");

            if ($this->call($command, $arguments) !== self::SUCCESS) {
                $this->error("{$command} failed - stopping");

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('Done. A language POEditor still shows at 0% needs seeding through /translations/add.');

        return self::SUCCESS;
    }
}
