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

it('decodes a complete surrogate pair but never half of one', function () {
    $strip = fn (string $text) => App\Actions\Helpers\Translations\Translate::stripJsonEscapes($text);

    expect($strip('\\ud83d\\ude00 smile'))->toBe('😀 smile')
        ->and($strip('<p>\\ud83c\\udf0e</p>'))->toBe('<p>🌎</p>')
        ->and($strip('lone \\ud83d half'))->toBe('lone \\ud83d half')
        ->and($strip('trailing \\ude00 half'))->toBe('trailing \\ude00 half')
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

it('never writes a repair translation back that is still escaped or is the untranslated source', function () {
    $usable = fn (?string $translated, string $source, string $code) => App\Actions\Maintenance\Catalogue\RepairEscapedDescriptions::isUsableTranslation($translated, $source, $code);

    expect($usable('<p>Hola<\\/p>', '<p>Hi</p>', 'es'))->toBeFalse()
        ->and($usable('<p>Hi</p>', '<p>Hi</p>', 'es'))->toBeFalse()
        ->and($usable('', '<p>Hi</p>', 'es'))->toBeFalse()
        ->and($usable(null, '<p>Hi</p>', 'es'))->toBeFalse()
        ->and($usable('<p>Hi</p>', '<p>Hi</p>', 'en-gb'))->toBeTrue()
        ->and($usable('<p>Hola</p>', '<p>Hi</p>', 'es'))->toBeTrue();
});

it('strips an echoed newline only when the source proves it cannot be a literal', function () {
    $translate = App\Actions\Helpers\Translations\Translate::make();

    expect($translate->unescapeJsonEchoes('18 cm<br>25 cm', '18 cm<br>\n25 cm'))->toBe("18 cm<br>\n25 cm")
        ->and($translate->unescapeJsonEchoes('C:\\path', 'C:\\chemin\nx'))->toBe('C:\\chemin\nx');

    /* Stored text is a different matter: there a backslash-n may always have been a literal, so the
       guard that gates the historic repair must keep refusing it. */
    expect(App\Actions\Helpers\Translations\Translate::hasOnlyJsonEchoEscapes('line\nbreak'))->toBeFalse();
});

class BrokenTranslationDriver implements TranslationDriver
{
    public function __construct(public array $config)
    {
    }

    public function translate(array $texts, string $sourceLang, string $targetLang): array
    {
        throw new RuntimeException('engine down');
    }
}

it('falls back to the source text on engine failure, but the interactive button gets an error', function () {
    config()->set('auto-translations.drivers.broken', ['class' => BrokenTranslationDriver::class]);
    $english = App\Models\Helpers\Language::firstWhere('code', 'en');
    $polish  = App\Models\Helpers\Language::firstWhere('code', 'pl');
    $text    = 'hello '.uniqid();

    expect(App\Actions\Helpers\Translations\Translate::make()->handle($text, $english, $polish, 'broken'))->toBe($text);

    expect(fn () => App\Actions\Helpers\Translations\Translate::make()->handle($text, $english, $polish, 'broken', throwOnFailure: true))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('sends the batch to ChatGPT as a json object, never a list', function () {
    Illuminate\Support\Facades\Http::fake([
        'api.openai.com/*' => Illuminate\Support\Facades\Http::response([
            'choices' => [['message' => ['content' => '{"0":"bonjour","1":"monde"}']]],
        ]),
    ]);

    $translated = (new App\Actions\Helpers\Translations\ChatGPT5Driver(['api_key' => 'x', 'max_tokens' => 16384]))
        ->translate(['a' => 'hello', 'b' => 'world'], 'en', 'fr');

    expect($translated)->toBe(['a' => 'bonjour', 'b' => 'monde']);

    Illuminate\Support\Facades\Http::assertSent(function (Illuminate\Http\Client\Request $request) {
        return $request['messages'][1]['content'] === '{"0":"hello","1":"world"}';
    });
});

it('reads a json reply wrapped in a markdown fence and sends the configured temperature', function () {
    Illuminate\Support\Facades\Http::fake([
        '*' => Illuminate\Support\Facades\Http::response([
            'choices' => [['message' => ['content' => "```json\n{\"0\":\"bonjour\"}\n```"]]],
            'usage'   => ['cost' => 0.0001],
        ]),
    ]);

    $driver = new App\Actions\Helpers\Translations\ChatGPT5Driver(['api_key' => 'x', 'max_tokens' => 16384, 'model' => 'anthropic/claude-haiku-4.5', 'temperature' => 0.2]);

    expect($driver->translate(['a' => 'hello'], 'en', 'fr'))->toBe(['a' => 'bonjour'])
        ->and($driver->lastUsage['cost'])->toBe(0.0001);

    Illuminate\Support\Facades\Http::assertSent(fn (Illuminate\Http\Client\Request $request) => $request['temperature'] === 0.2 && $request['model'] === 'anthropic/claude-haiku-4.5');
});

it('lists the fallback models after the driver model when going through openrouter', function () {
    config()->set('services.openrouter.api_key', 'or-key');
    $fallbacks = ['gpt-4o-mini', 'google/gemini-3.1-flash-lite'];

    expect((new App\Actions\Helpers\Translations\ChatGPT5Driver(['model' => 'gpt-5-nano', 'fallback_models' => $fallbacks]))->modelParameters())
        ->toBe(['models' => ['openai/gpt-5-nano', 'openai/gpt-4o-mini', 'google/gemini-3.1-flash-lite']])
        ->and((new App\Actions\Helpers\Translations\ChatGPT5Driver(['model' => 'gpt-4o-mini', 'fallback_models' => $fallbacks]))->modelParameters())
        ->toBe(['models' => ['openai/gpt-4o-mini', 'google/gemini-3.1-flash-lite']])
        ->and((new App\Actions\Helpers\Translations\ChatGPT5Driver(['model' => 'gpt-5-nano', 'fallback_models' => []]))->modelParameters())
        ->toBe(['model' => 'openai/gpt-5-nano']);

    config()->set('services.openrouter.api_key', null);

    expect((new App\Actions\Helpers\Translations\ChatGPT5Driver(['model' => 'gpt-5-nano']))->modelParameters())
        ->toBe(['model' => 'gpt-5-nano']);
});

it('redoes a translation jev grades as weak with the retry driver, and keeps a good one', function (float $jevScore, string $expected, int $translationCalls) {
    config()->set('services.openrouter.api_key', 'or-key');
    Illuminate\Support\Facades\Http::fake([
        'openrouter.ai/api/alpha/decisions' => Illuminate\Support\Facades\Http::response(['answers' => ['quality' => ['score' => $jevScore]]]),
        'openrouter.ai/api/v1/chat/completions' => Illuminate\Support\Facades\Http::sequence()
            ->push(['choices' => [['message' => ['content' => '{"0":"from gemini"}']]]])
            ->push(['choices' => [['message' => ['content' => '{"0":"from sonnet"}']]]]),
    ]);

    $english = App\Models\Helpers\Language::firstWhere('code', 'en');
    $french  = App\Models\Helpers\Language::firstWhere('code', 'fr');

    expect(App\Actions\Helpers\Translations\Translate::make()->handle('hello '.uniqid(), $english, $french, 'catalogue'))->toBe($expected);

    $translationRequests = Illuminate\Support\Facades\Http::recorded(fn (Illuminate\Http\Client\Request $request) => str_ends_with($request->url(), 'chat/completions'))->values();
    expect($translationRequests)->toHaveCount($translationCalls)
        ->and($translationRequests[0][0]['models'][0])->toBe('google/gemini-3.1-flash-lite');

    if ($translationCalls === 2) {
        expect($translationRequests[1][0]['models'][0])->toBe('anthropic/claude-sonnet-5.5');
    }
})->with([
    'weak'         => [0.8, 'from sonnet', 2],
    'publishable'  => [3.6, 'from gemini', 1],
]);

it('translates email chats with the checked driver and website chats with the default one', function () {
    $message = fn (App\Enums\CRM\Livechat\ChatChannelEnum $channel) => (new App\Models\Chat\ChatMessage())
        ->setRelation('chatSession', (new App\Models\Chat\ChatSession())->forceFill(['channel' => $channel]));

    expect(App\Actions\Chat\ChatSession\TranslateChatMessage::make()->translationDriver($message(App\Enums\CRM\Livechat\ChatChannelEnum::EMAIL)))->toBe('email')
        ->and(App\Actions\Chat\ChatSession\TranslateChatMessage::make()->translationDriver($message(App\Enums\CRM\Livechat\ChatChannelEnum::WEBSITE)))->toBeNull();
});

it('keeps the first translation when the stronger retry comes back untranslated, and never caches an untranslated text', function () {
    config()->set('services.openrouter.api_key', 'or-key');
    Illuminate\Support\Facades\Http::fake([
        'openrouter.ai/api/alpha/decisions'     => Illuminate\Support\Facades\Http::response(['answers' => ['quality' => ['score' => 1.2]]]),
        'openrouter.ai/api/v1/chat/completions' => Illuminate\Support\Facades\Http::sequence()
            ->push(['choices' => [['message' => ['content' => '{"0":"from gemini"}']]]])
            ->push([], 500),
    ]);

    $english = App\Models\Helpers\Language::firstWhere('code', 'en');
    $french  = App\Models\Helpers\Language::firstWhere('code', 'fr');
    $source  = 'hello '.uniqid();

    expect(App\Actions\Helpers\Translations\Translate::make()->handle($source, $english, $french, 'catalogue'))->toBe('from gemini');

    Illuminate\Support\Facades\Http::fake(['*' => Illuminate\Support\Facades\Http::response([], 500)]);
    $untranslated = 'bye '.uniqid();
    App\Actions\Helpers\Translations\Translate::make()->handle($untranslated, $english, $french, 'sonnet');

    expect(Illuminate\Support\Facades\Cache::has('translate:sonnet:'.sha1('en|fr|'.$untranslated)))->toBeFalse();
});

it('sends only an openai model when there is no openrouter key, and reads json wrapped in prose', function () {
    config()->set('services.openrouter.api_key', null);

    expect((new App\Actions\Helpers\Translations\ChatGPT5Driver(config('auto-translations.drivers.sonnet')))->modelParameters())->toBe(['model' => 'gpt-4o-mini']);

    Illuminate\Support\Facades\Http::fake(['*' => Illuminate\Support\Facades\Http::response([
        'choices' => [['message' => ['content' => "Here is the translation:\n```JSON\n{\"0\":\"bonjour\"}\n```"]]],
    ])]);

    expect((new App\Actions\Helpers\Translations\ChatGPT5Driver(['max_tokens' => 16384]))->translate(['a' => 'hello'], 'en', 'fr'))->toBe(['a' => 'bonjour']);
});

it('asks jev to choose between the ngram shortlist, the conversation language and the visitor country languages', function () {
    config()->set('services.openrouter.api_key', 'or-key');
    Illuminate\Support\Facades\Http::fake([
        'openrouter.ai/api/alpha/decisions' => Illuminate\Support\Facades\Http::response(['answers' => ['language' => ['choice' => 'pt', 'probabilities' => ['pt' => 0.97], 'confidence' => 0.97]]]),
    ]);

    $slovak = App\Models\Helpers\Language::firstWhere('code', 'sk');

    expect(App\Actions\Helpers\Translations\DetectLanguageWithJev::make()->handle('Obrigado, até logo', [$slovak], 'PT   ')->code)->toBe('pt');

    Illuminate\Support\Facades\Http::assertSent(function (Illuminate\Http\Client\Request $request) {
        $options = array_keys($request['questions']['language']['criteria']);

        return $request['questions']['language']['type'] === 'choice'
            && in_array('pt', $options) && in_array('sk', $options) && in_array('en', $options)
            && $request['state']['visitor_country'] === 'PT';
    });
});

it('maps the detector codes onto ours and falls back to the conversation language without jev', function () {
    $detector = App\Actions\Helpers\Translations\DetectLanguageWithJev::make();

    expect($detector->ourCode('pt-BR', ['pt', 'pt-br']))->toBe('pt')
        ->and($detector->ourCode('zh-Hans', ['zh-Hans', 'zh-Hant']))->toBe('zh-Hans')
        ->and($detector->ourCode('pt-BR', ['pt-br']))->toBe('pt-br')
        ->and($detector->ourCode('xx-Yyyy', ['en']))->toBeNull();

    config()->set('services.openrouter.api_key', null);
    $spanish = App\Models\Helpers\Language::firstWhere('code', 'es');

    expect(App\Actions\Helpers\Translations\DetectLanguageWithJev::run('Hola', [$spanish])->code)->toBe('es')
        ->and(App\Actions\Helpers\Translations\DetectLanguageWithJev::run('Hola'))->toBeNull();
});

class NulTranslationDriver implements TranslationDriver
{
    public function __construct(public array $config)
    {
    }

    public function translate(array $texts, string $sourceLang, string $targetLang): array
    {
        return array_map(fn (string $text) => "Jab\0on $text", $texts);
    }
}

it('strips NUL characters from fresh and cached translations', function () {
    config()->set('auto-translations.drivers.nul', ['class' => NulTranslationDriver::class]);
    $english = (new App\Models\Helpers\Language())->forceFill(['code' => 'en']);
    $french  = (new App\Models\Helpers\Language())->forceFill(['code' => 'fr']);
    $translate = App\Actions\Helpers\Translations\Translate::make();

    expect($translate->handle('soap', $english, $french, 'nul'))->toBe('Jabon soap');

    Illuminate\Support\Facades\Cache::put('translate:nul:'.sha1('en|fr|hello'), "bon\0jour");

    expect($translate->handle('hello', $english, $french, 'nul'))->toBe('bonjour');
});
