<?php

namespace App\Services;

use Symfony\Component\Finder\Finder;
use Vemcogroup\Translation\Translation as BaseTranslation;

class Translation extends BaseTranslation
{
    public function scan($mergeKeys = false): int
    {
        $allMatches = [];
        $finder = new Finder();

        $finder->in(base_path())
            ->exclude(config('translation.excluded_directories'))
            ->name(config('translation.extensions'))
            ->followLinks()
            ->files();

        $functions = config('translation.functions');
        $pattern =
            '[^\w]' .
            '(?<!->)' .
            '(?:' . implode('|', $functions) . ')' .
            "\(" .
            "\s*" .
            '(?:' .
            "'(.+)'" .
            '|' .
            "`(.+)`" .
            '|' .
            "\"(.+)\"" .
            ')' .
            "\s*" .
            "[\),]"
        ;

        foreach ($finder as $file) {
            if (preg_match_all("/$pattern/siU", $file->getContents(), $matches)) {
                unset($matches[0]);
                $allMatches[$file->getRelativePathname()] =
                    array_filter(
                        array_merge(...$matches),
                        function ($value) {
                            return (!is_null($value)) && !empty($value);
                        }
                    );
            }
        }

        $collapsedKeys = $this->normalizeScannedKeys(collect($allMatches)->collapse());
        $keys = $collapsedKeys->combine($collapsedKeys->map(fn ($value) => trim($value)));

        if ($mergeKeys) {
            $content = $this->getFileContent();
            $keys = $content->union(
                $keys->filter(function ($key) use ($content) {
                    return !$content->has($key);
                })
            );
        }

        file_put_contents($this->baseFilename, json_encode($keys->sortKeys(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $keys->count();
    }
}
