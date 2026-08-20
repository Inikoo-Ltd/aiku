<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 16 Aug 2026 11:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use VildanBina\LaravelAutoTranslation\Contracts\TranslationDriver;
use VildanBina\LaravelAutoTranslation\Services\TranslationEngineService;
use VildanBina\LaravelAutoTranslation\TranslationWorkflowService;

// ponytail: pins only the vendor surface App\Actions\Helpers\Translations\Translate depends on,
// so a laravel-auto-translation upgrade that changes it fails here instead of in production.

class FakeTranslationDriver implements TranslationDriver
{
    public static array $seenTexts = [];

    public function __construct(public array $config)
    {
    }

    public function translate(array $texts, string $sourceLang, string $targetLang): array
    {
        self::$seenTexts = $texts;

        return array_map(fn (string $text) => $text.' [fr]', $texts);
    }
}

it('resolves a custom driver class from config and round-trips in-memory texts', function () {
    config()->set('auto-translations.drivers.fake', [
        'class'   => FakeTranslationDriver::class,
        'api_key' => 'not-used',
    ]);

    $translated = (new TranslationWorkflowService(new TranslationEngineService()))
        ->setInMemoryTexts(['text_to_translate' => 'hello'])
        ->translate('en', 'fr', 'fake');

    expect($translated)->toBe(['text_to_translate' => 'hello [fr]']);
});

it('masks laravel placeholders so drivers never see them', function () {
    config()->set('auto-translations.drivers.fake', ['class' => FakeTranslationDriver::class]);

    $translated = (new TranslationWorkflowService(new TranslationEngineService()))
        ->setInMemoryTexts(['text_to_translate' => 'hello :name'])
        ->translate('en', 'fr', 'fake');

    expect(FakeTranslationDriver::$seenTexts['text_to_translate'])->not->toContain(':name')
        ->and($translated['text_to_translate'])->toBe('hello :name [fr]');
});
