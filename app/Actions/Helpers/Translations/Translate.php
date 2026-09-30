<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Sept 2025 11:33:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Translations;

use App\Actions\Helpers\AI\AskJev;
use App\Actions\OrgAction;
use App\Models\Helpers\Language;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use VildanBina\LaravelAutoTranslation\TranslationWorkflowService;
use VildanBina\LaravelAutoTranslation\Services\TranslationEngineService;

class Translate extends OrgAction
{
    use AsAction;

    /**
     * @throws \Exception
     */
    public function handle(?string $text, Language $languageFrom, Language $languageTo, ?string $translationDriver = null, bool $throwOnFailure = false): string
    {
        try {
            if ($text == null || $text == '' || $languageFrom->code == $languageTo->code) {
                return $text ?? '';
            }

            $cacheKey          = 'translate:'.sha1($languageFrom->code.'|'.$languageTo->code.'|'.$text);
            $cachedTranslation = Cache::get($cacheKey);
            if ($cachedTranslation !== null) {
                return $this->unescapeJsonEchoes($text, str_replace("\0", '', $cachedTranslation));
            }

            if (app()->environment('local') && !config('app.sandbox.translate')) {
                return $text;
            }
            $translationDriver ??= config('auto-translations.default_driver');
            $translated        = $this->translateWith($text, $languageFrom, $languageTo, $translationDriver);

            $qualityCheck = config("auto-translations.drivers.$translationDriver.quality_check");
            if ($qualityCheck && $this->isBelowQuality($text, $translated, $languageFrom, $languageTo, $qualityCheck['min_score'])) {
                $retried    = rescue(fn () => $this->translateWith($text, $languageFrom, $languageTo, $qualityCheck['retry_driver']), $text);
                $translated = $retried !== $text ? $retried : $translated;
            }

            if ($translated !== $text) {
                $cacheTtlHours = mb_strlen($translated) < 32 ? 1440 : (mb_strlen($translated) < 256 ? 480 : 72);
                Cache::put($cacheKey, $translated, now()->addHours($cacheTtlHours));
            }

            return $translated;
        } catch (\Throwable $e) {
            Sentry::captureMessage($e->getMessage());
            if ($throwOnFailure) {
                abort(503, __('Translation failed, please try again'));
            }

            return $text;
        }
    }

    public function translateWith(string $text, Language $languageFrom, Language $languageTo, string $translationDriver): string
    {
        $translationWorkflowService = new TranslationWorkflowService(new TranslationEngineService());
        $translationWorkflowService->setInMemoryTexts(['text_to_translate' => $text]);

        $translatedTexts = $translationWorkflowService->translate($languageFrom->code, $languageTo->code, $translationDriver);

        return str_replace("\0", '', $this->unescapeJsonEchoes($text, Arr::get($translatedTexts, 'text_to_translate', $text)));
    }

    /**
     * Jev grades the cheap model's translation for a fraction of a cent; the drivers that ask for
     * it redo the weak ones with a stronger model. No verdict (no key, Jev down) keeps the translation.
     */
    public function isBelowQuality(string $source, string $translated, Language $languageFrom, Language $languageTo, float $minScore): bool
    {
        $verdict = Arr::get(AskJev::run(
            [
                'source_language' => $languageFrom->code,
                'target_language' => $languageTo->code,
                'source'          => $source,
                'translation'     => $translated,
            ],
            ['quality' => [
                'type'         => 'score',
                'instructions' => 'How good is this translation for a native speaker of the target language, ready to publish on a web shop or send to a customer?',
                'criteria'     => [
                    'Unusable: wrong language, left untranslated, or meaning lost',
                    'Serious errors: wrong meaning in parts, broken grammar, missing content or broken HTML',
                    'Understandable but with errors a native speaker would fix',
                    'Good: only minor slips',
                    'Publishable as is: accurate and natural',
                ],
            ]]
        ), 'quality');

        return isset($verdict['score']) && $verdict['score'] < $minScore;
    }

    /**
     * LLM translation drivers round-trip through a JSON payload and frequently echo the
     * JSON escaping back as literal characters, storing style=\\" and <\\/strong> in the text.
     */
    public function unescapeJsonEchoes(string $original, string $translated): string
    {
        if (str_contains($original, '\\') || json_validate($original)) {
            return $translated;
        }

        return self::stripJsonEscapes($translated);
    }

    /**
     * True only when every backslash in the text is part of a JSON escape a translation driver
     * could have echoed. Text carrying any other backslash is ambiguous - a literal one cannot be
     * told apart from a double-escaped one - so callers without a clean source must leave it alone.
     *
     * Deliberately narrower than what stripJsonEscapes can undo: \n is excluded because in stored
     * text it may always have been a literal, while stripJsonEscapes only ever sees it behind a
     * source known to carry no backslash at all, where it can only be an echo.
     */
    public static function hasOnlyJsonEchoEscapes(string $text): bool
    {
        return substr_count($text, '\\') === preg_match_all('/\\\\(?:["\\/\']|u[0-9a-fA-F]{4})/', $text);
    }

    public static function stripJsonEscapes(string $text): string
    {
        for ($pass = 0; $pass < 5; $pass++) {
            $stripped = preg_replace_callback(
                '/\\\\u([dD][89abAB][0-9a-fA-F]{2})\\\\u([dD][c-fC-F][0-9a-fA-F]{2})|\\\\u([0-9a-fA-F]{4})/',
                function (array $m) {
                    /* A complete high/low pair can only ever have meant one emoji, so it decodes.
                       A surrogate on its own cannot, and neither can a null: both stay as they are
                       rather than becoming half a character. */
                    if (($m[3] ?? '') === '') {
                        $codepoint = 0x10000 + ((hexdec($m[1]) - 0xD800) << 10) + (hexdec($m[2]) - 0xDC00);

                        return mb_chr($codepoint, 'UTF-8') ?: $m[0];
                    }

                    $codepoint = hexdec($m[3]);
                    if ($codepoint === 0 || ($codepoint >= 0xD800 && $codepoint <= 0xDFFF)) {
                        return $m[0];
                    }

                    return mb_chr($codepoint, 'UTF-8') ?: $m[0];
                },
                str_replace(
                    ['\\"', '\\/', "\\'", '\\n', '\\r', '\\t'],
                    ['"', '/', "'", "\n", "\r", "\t"],
                    $text
                )
            );

            if ($stripped === $text) {
                return $text;
            }

            $text = $stripped;
        }

        return $text;
    }

    public function getCommandSignature(): string
    {
        return 'translate {languageFrom} {languageTo} {text}';
    }


    public function rules(): array
    {
        return [
            'text' => ['required', 'string']
        ];
    }

    /**
     * @throws \Exception
     */
    public function asController(string $languageFrom, string $languageTo, ActionRequest $request): string
    {
        set_time_limit(100);

        $this->initialisationFromGroup(group(), $request);
        $languageFrom = Language::where('code', $languageFrom)->first();
        $languageTo   = Language::where('code', $languageTo)->first();
        $text         = Arr::get($this->validatedData, 'text');


        return $this->handle($text, $languageFrom, $languageTo, throwOnFailure: true);
    }

    /**
     * @throws \Exception
     */
    public function asCommand($command): void
    {
        $text         = $command->argument('text');
        $languageFrom = Language::where('code', $command->argument('languageFrom'))->firstOrFail();
        $languageTo   = Language::where('code', $command->argument('languageTo'))->firstOrFail();

        $translation = $this->handle($text, $languageFrom, $languageTo);
        $command->info($text.' -> '.$translation);
    }


}
