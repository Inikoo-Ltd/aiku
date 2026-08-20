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

it('strips json escaping echoed back by translation drivers', function () {
    $translate = App\Actions\Helpers\Translations\Translate::make();

    expect($translate->unescapeJsonEchoes(
        '<p><strong>Hecate</strong><span style="color: #333333;">oil</span></p>',
        '<p><strong>Hécate<\/strong><span style=\"color: #333333;\">huile</span></p>'
    ))->toBe('<p><strong>Hécate</strong><span style="color: #333333;">huile</span></p>');
});

it('leaves translations alone when the source itself carries backslashes or json', function () {
    $translate = App\Actions\Helpers\Translations\Translate::make();

    expect($translate->unescapeJsonEchoes('C:\\path', 'C:\\chemin'))->toBe('C:\\chemin')
        ->and($translate->unescapeJsonEchoes('{"q":"a"}', '{"q":"une"}'))->toBe('{"q":"une"}');
});

it('strips nested json escaping and unicode escapes down to plain text', function () {
    expect(App\Actions\Helpers\Translations\Translate::stripJsonEscapes(
        '<ul style=\\\\"padding: 0px\\\\"><li>Bag \\u2013 Nomad<\/li>all\\\'interno</ul>'
    ))->toBe('<ul style="padding: 0px"><li>Bag – Nomad</li>all\'interno</ul>');
});

it('never eats emoji surrogate pairs or null escapes', function () {
    $strip = fn (string $text) => App\Actions\Helpers\Translations\Translate::stripJsonEscapes($text);

    expect($strip('\\ud83d\\ude00 smile'))->toBe('\\ud83d\\ude00 smile')
        ->and($strip('\\u0000null'))->toBe('\\u0000null')
        ->and($strip('bullet \\u2022 here'))->toBe('bullet • here');
});

it('refuses to strip text whose backslashes are ambiguous', function () {
    $safe = fn (string $text) => App\Actions\Helpers\Translations\Translate::hasOnlyJsonEchoEscapes($text);

    expect($safe('<p style=\\"color: red\\">a<\\/p>'))->toBeTrue()
        ->and($safe('bullet \\u2022 here'))->toBeTrue()
        ->and($safe('A\\\\/B'))->toBeFalse()
        ->and($safe('C:\\path\\to'))->toBeFalse()
        ->and($safe('regex \\d+ digits'))->toBeFalse()
        ->and($safe('no backslash at all'))->toBeTrue();
});
