<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class ImportSearchSynonyms
{
    use AsAction;

    public string $commandSignature = 'search:import-synonyms {file : JSON file: [{"language":"en","words":["misspelling","real word"]}, ...]}';

    public string $commandDescription = 'Bulk-import curated search synonyms into the per-language Typesense synonym sets';

    public function asCommand(Command $command): int
    {
        $path = $command->argument('file');
        if (!is_file($path)) {
            $command->error("File not found: $path");

            return 1;
        }

        $entries = json_decode(file_get_contents($path), true);
        if (!is_array($entries)) {
            $command->error('File is not a JSON array');

            return 1;
        }

        $imported = 0;
        $failed   = 0;
        foreach ($entries as $index => $entry) {
            $language = Arr::get($entry, 'language');
            $words    = Arr::get($entry, 'words', []);

            if (!is_string($language) || $language === '' || !is_array($words) || count($words) < 2) {
                $command->warn("#$index skipped, needs language + at least 2 words: ".json_encode($entry));
                $failed++;
                continue;
            }

            $result = StoreSearchSynonym::make()->handle($language, $words);
            if ($result['status'] >= 200 && $result['status'] < 300) {
                $command->line("[$language] {$result['id']}: ".implode(', ', $result['synonyms']));
                $imported++;
            } else {
                $command->warn("#$index failed with HTTP {$result['status']}: ".json_encode($entry));
                $failed++;
            }
        }

        $command->info("Imported $imported synonyms".($failed ? ", $failed failed/skipped" : ''));

        return $failed ? 1 : 0;
    }
}
