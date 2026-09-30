<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Translations;

use App\Actions\Helpers\AI\AskJev;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\Helpers\Language;
use Illuminate\Support\Arr;
use LanguageDetection\Language as NgramDetector;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The n-gram detector is free and usually has the answer among its first few guesses, but its
 * scores are similarities, not confidence (Slovak 0.76 vs Czech 0.72). Its shortlist, plus the
 * languages the conversation already points to, becomes the options of a Jev choice question.
 * Tested on 200 real customer messages: 100% against 92% for the gpt-4o-mini detector, in half
 * the time. Jev's choice is taken even at low confidence, as falling back to the conversation's
 * language did worse. Without Jev (no key, or down) the conversation's language stands, as the
 * n-gram's own first guess on a short message can be anything ("Hola" read as Ndonga).
 */
class DetectLanguageWithJev
{
    use AsAction;

    private const int NGRAM_CANDIDATES = 5;

    /**
     * Loading the n-gram profiles of every language takes ~50 ms and ~19 MB; they never change.
     */
    private static ?NgramDetector $ngramDetector = null;

    /**
     * @var array<string, array<int, string>>
     */
    public const array COUNTRY_LANGUAGES = [
        'GB' => ['en'], 'IE' => ['en'], 'US' => ['en'], 'CA' => ['en', 'fr'], 'AU' => ['en'], 'NZ' => ['en'],
        'ES' => ['es'], 'MX' => ['es'], 'AR' => ['es'], 'CO' => ['es'], 'CL' => ['es'], 'PE' => ['es'],
        'FR' => ['fr'], 'LU' => ['fr', 'de'], 'BE' => ['nl', 'fr'], 'CH' => ['de', 'fr', 'it'],
        'DE' => ['de'], 'AT' => ['de'], 'LI' => ['de'], 'IT' => ['it'], 'SM' => ['it'], 'MT' => ['en'],
        'PT' => ['pt'], 'BR' => ['pt'], 'NL' => ['nl'], 'SE' => ['sv'], 'FI' => ['fi', 'sv'],
        'BG' => ['bg'], 'UA' => ['uk'], 'HU' => ['hu'], 'PL' => ['pl'], 'RO' => ['ro'], 'MD' => ['ro'],
        'CZ' => ['cs'], 'SK' => ['sk'], 'HR' => ['hr'], 'BA' => ['hr'], 'SI' => ['sl'], 'GR' => ['el'],
        'DK' => ['da'], 'NO' => ['nb'], 'LT' => ['lt'], 'LV' => ['lv'], 'EE' => ['et'],
    ];

    /**
     * @param  array<int, Language|null>  $contextLanguages  languages the conversation points to (customer's last one, shop), strongest first
     */
    public function handle(?string $text, array $contextLanguages = [], ?string $countryCode = null): ?Language
    {
        $answer = $this->ask($text, $contextLanguages, $countryCode);

        return $answer ? Language::where('code', $answer['choice'])->first() : null;
    }

    /**
     * A customer rarely switches language mid-conversation, so the language they wrote in last
     * comes first, then the chat's and the shop's, then the visitor's country when known.
     */
    public static function inConversation(?string $text, ChatSession|MetaChatSession $session): ?Language
    {
        return static::run(
            $text,
            [$session->userLanguage, $session->language, $session->shop?->language],
            $session instanceof ChatSession ? $session->geo_country_code : null
        );
    }

    /**
     * @param  array<int, Language|null>  $contextLanguages
     * @return array{choice: string, confidence: float, candidates: array<int, string>}|null
     */
    public function ask(?string $text, array $contextLanguages = [], ?string $countryCode = null): ?array
    {
        $text = trim((string) $text);

        if ($text === '' || is_numeric($text)) {
            return null;
        }

        $contextCodes = array_filter(Arr::pluck($contextLanguages, 'code'));
        $countryCodes = self::COUNTRY_LANGUAGES[strtoupper(trim((string) $countryCode))] ?? [];

        $ngramCodes = $this->ngramCandidates($text);
        $known      = Language::whereIn('code', [...collect($ngramCodes)->flatMap(fn (string $code) => [$code, strtolower($code), strtolower(explode('-', $code)[0])]), ...$contextCodes, ...$countryCodes, 'en'])->pluck('name', 'code');
        $ngramCodes = array_filter(array_map(fn (string $code) => $this->ourCode($code, $known->keys()->all()), $ngramCodes));
        $names      = $known->only(array_unique([...$ngramCodes, ...$contextCodes, ...$countryCodes, 'en']));

        if ($names->count() === 1) {
            return ['choice' => $names->keys()->first(), 'confidence' => 1.0, 'candidates' => $names->keys()->all()];
        }

        $answer = Arr::get(AskJev::run(
            array_filter([
                'message'                  => mb_substr($text, 0, 1500),
                'conversation_language'    => Arr::first($contextCodes),
                'visitor_country'          => trim((string) $countryCode),
            ]),
            ['language' => [
                'type'         => 'choice',
                'instructions' => 'Which language is the customer message written in? Judge the message itself; the context only helps when the message is too short to tell.',
                'criteria'     => $names->mapWithKeys(fn (string $name, string $code) => [$code => "Written in $name"])->all(),
            ]]
        ), 'language');

        if (!isset($answer['choice'])) {
            $contextCode = Arr::first($contextCodes);

            return $contextCode ? ['choice' => $contextCode, 'confidence' => 0.0, 'candidates' => $names->keys()->all()] : null;
        }

        return [
            'choice'     => $answer['choice'],
            'confidence' => (float) ($answer['confidence'] ?? Arr::get($answer, 'probabilities.'.$answer['choice'], 0)),
            'candidates' => $names->keys()->all(),
        ];
    }

    /**
     * @return array<int, string> the detector's codes (pt-BR, zh-Hans, ...), best guess first
     */
    public function ngramCandidates(string $text): array
    {
        if (!self::$ngramDetector) {
            self::$ngramDetector = new NgramDetector();
            self::$ngramDetector->setMaxNgrams(9000);
        }

        return array_keys(self::$ngramDetector->detect($text)->limit(0, self::NGRAM_CANDIDATES)->close());
    }

    /**
     * The plain language when we have it (pt-BR is Portuguese), the variant when only that exists
     * (zh-Hans: there is no zh), matched regardless of case (pt-br).
     *
     * @param  array<int, string>  $ourCodes
     */
    public function ourCode(string $detectorCode, array $ourCodes): ?string
    {
        $base = strtolower(explode('-', $detectorCode)[0]);

        if (in_array($base, $ourCodes, true)) {
            return $base;
        }

        return collect($ourCodes)->first(fn (string $code) => strcasecmp($code, $detectorCode) === 0);
    }
}
