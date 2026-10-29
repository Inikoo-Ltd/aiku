<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\WritingQuality;

use App\Actions\Helpers\AI\AskJev;
use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * The "Polish" button of the customer service composers (HELP-4382): Sonnet corrects the grammar,
 * spelling and flow of a reply staff already wrote, in its own language and voice. Pictures, merge
 * fields, links and emails are swapped for placeholders the model cannot touch and must come back
 * in the same order; every word with a digit (order numbers, codes, prices, dates), every currency
 * sign and every line break must come back as they were; no new formatting may appear; and Jev must
 * confirm the meaning, language and voice did not change. Anything that fails, Jev not answering
 * included, keeps the text as the person wrote it.
 */
class PolishWriting
{
    use AsAction;
    use WithAIGateway;

    public const string MODEL = 'anthropic/claude-sonnet-5.5';

    public const array CHANNELS = ['chat', 'email', 'whatsapp'];

    private const int MAX_CHARACTERS = 6000;

    private const int POLISHES_PER_TEN_MINUTES = 60;

    /**
     * @var array<string, float> lowest probability Jev must give each question
     */
    private const array JEV_THRESHOLDS = ['same_meaning' => 0.7, 'same_language' => 0.7, 'same_voice' => 0.5];

    private const string PROTECTED_PATTERN = '/\[image:[A-Za-z0-9-]{1,64}\]|\[\[[^\]]*\]\]|\{\{[^}]*\}\}|\{[A-Za-z_][A-Za-z0-9_.]*\}|https?:\/\/[^\s<>]*[^\s<>.,;:!?)\]*_~`\'"]|www\.[^\s<>]*[^\s<>.,;:!?)\]*_~`\'"]|[A-Za-z0-9][\w.+-]*@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+/u';

    /**
     * @return array{status: 'polished'|'unchanged'|'kept', text: string}
     * @throws ValidationException
     */
    public function handle(string $text, string $channel): array
    {
        if (trim($text) === '') {
            return ['status' => 'unchanged', 'text' => $text];
        }

        if (mb_strlen($text) > self::MAX_CHARACTERS) {
            throw ValidationException::withMessages(['text' => __('This message is too long to polish in one go.')]);
        }

        if (!$this->usesOpenRouter()) {
            throw ValidationException::withMessages(['text' => __('The AI gateway is not set up. Add OPENROUTER_API_KEY to the environment.')]);
        }

        [$masked, $tokens] = $this->mask($text);

        try {
            $response = $this->aiRequest()
                ->timeout(20)
                ->post('chat/completions', [
                    'model'           => $this->aiModel(self::MODEL),
                    'messages'        => [
                        ['role' => 'system', 'content' => $this->instructions()],
                        ['role' => 'user', 'content' => "Channel: $channel\n\n<message>\n$masked\n</message>"],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'reasoning'       => ['effort' => 'low'],
                    'max_tokens'      => 8000,
                ])
                ->throw();
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['text' => __('The AI gateway did not answer: :error', ['error' => Str::limit($e->getMessage(), 200)])]);
        }

        $content = (string) $response->json('choices.0.message.content');
        $answer  = preg_match('/\{.*\}/s', $content, $match) ? json_decode($match[0], true) : null;

        if (!is_array($answer) || !is_string($answer['text'] ?? null)) {
            throw ValidationException::withMessages(['text' => __('The AI gateway answered without a usable text. Try again.')]);
        }

        $polishedMasked = trim($answer['text']);

        if ($this->normalised($polishedMasked) === $this->normalised($masked)) {
            return ['status' => 'unchanged', 'text' => $text];
        }

        $polished = $this->unmask($polishedMasked, $tokens);

        if ($polished === null || !$this->keepsDetails($masked, $polishedMasked) || !$this->keepsFormatting($masked, $polishedMasked) || !$this->keepsLayout($masked, $polishedMasked) || !$this->isConfirmedByJev($text, $polished)) {
            return ['status' => 'kept', 'text' => $text];
        }

        return ['status' => 'polished', 'text' => $polished];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() instanceof User;
    }

    public function rules(): array
    {
        return [
            'text'    => ['required', 'string'],
            'channel' => ['required', 'string', Rule::in(self::CHANNELS)],
        ];
    }

    /**
     * @return array{status: 'polished'|'unchanged'|'kept', text: string}
     * @throws ValidationException
     */
    public function asController(ActionRequest $request): array
    {
        $key = 'writing-polish:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, self::POLISHES_PER_TEN_MINUTES)) {
            throw ValidationException::withMessages(['text' => __('Too many polishes in a short time. Try again in a few minutes.')]);
        }

        RateLimiter::hit($key, 600);

        return $this->handle($request->validated('text'), $request->validated('channel'));
    }

    /**
     * @param  array{status: 'polished'|'unchanged'|'kept', text: string}  $result
     */
    public function jsonResponse(array $result): JsonResponse
    {
        return response()->json($result);
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    public function mask(string $text): array
    {
        $tokens = [];
        $masked = preg_replace_callback(self::PROTECTED_PATTERN, function (array $match) use (&$tokens) {
            $tokens[] = $match[0];

            return '⟦'.count($tokens).'⟧';
        }, $text);

        return [(string) $masked, $tokens];
    }

    /**
     * Null when the model dropped, repeated, reordered or invented a placeholder.
     *
     * @param  array<int, string>  $tokens
     */
    public function unmask(string $masked, array $tokens): ?string
    {
        preg_match_all('/⟦(\d+)⟧/u', $masked, $found);

        if (array_map('intval', $found[1]) !== ($tokens === [] ? [] : range(1, count($tokens)))) {
            return null;
        }

        return preg_replace_callback('/⟦(\d+)⟧/u', fn (array $match) => $tokens[(int) $match[1] - 1], $masked);
    }

    /**
     * Every word with a digit in it (order numbers, product codes, prices, dates, quantities) and
     * every currency sign comes back exactly, as many times as before.
     */
    public function keepsDetails(string $original, string $polished): bool
    {
        $details = function (string $text): array {
            $words = preg_split('/\s+/u', preg_replace('/⟦\d+⟧/u', ' ', $text), -1, PREG_SPLIT_NO_EMPTY);
            $words = array_filter(array_map(fn (string $word) => trim($word, ".,;:!?()[]{}\"'«»¿¡*_~`"), $words), fn (string $word) => preg_match('/\d/u', $word));
            $signs = preg_match_all('/[€£$%¥₽]/u', $text, $match) ? $match[0] : [];
            $counts = array_count_values([...array_values($words), ...$signs]);
            ksort($counts);

            return $counts;
        };

        return $details($original) === $details($polished);
    }

    /**
     * The same number of lines, and a picture or link that stood on a line of its own still does,
     * so lists keep their items and pictures keep showing as pictures.
     */
    public function keepsLayout(string $original, string $polished): bool
    {
        if (substr_count($original, "\n") !== substr_count($polished, "\n")) {
            return false;
        }

        $standalone = fn (string $text) => collect(explode("\n", $text))->map(fn (string $line) => trim($line))->filter(fn (string $line) => preg_match('/^⟦\d+⟧$/u', $line))->values()->all();

        return $standalone($original) === $standalone($polished);
    }

    public function keepsFormatting(string $original, string $polished): bool
    {
        return collect(['*', '_', '~', '`'])->every(fn (string $mark) => substr_count($polished, $mark) <= substr_count($original, $mark));
    }

    private function isConfirmedByJev(string $original, string $polished): bool
    {
        $yes = ['true' => 'Yes', 'false' => 'No'];

        $answers = AskJev::run(['original' => $original, 'polished' => $polished], [
            'same_meaning'  => [
                'type'         => 'noul',
                'instructions' => 'Does the polished message say exactly what the original says: the same facts, answers, promises, requests and apologies, with nothing added and nothing left out?',
                'criteria'     => ['true' => 'Same content, only the wording is better', 'false' => 'Something was added, dropped or changed in meaning'],
            ],
            'same_language' => [
                'type'         => 'noul',
                'instructions' => 'Is the polished message written in the same language as the original?',
                'criteria'     => $yes,
            ],
            'same_voice'    => [
                'type'         => 'noul',
                'instructions' => 'Does the polished message keep the personal, friendly voice of the original, without becoming stiff, overly formal or salesy?',
                'criteria'     => $yes,
            ],
        ]);

        return $answers !== null && collect(self::JEV_THRESHOLDS)
            ->every(fn (float $threshold, string $question) => is_numeric(Arr::get($answers, "$question.noul")) && (float) Arr::get($answers, "$question.noul") >= $threshold);
    }

    private function normalised(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Kept the same for every call so the gateway can cache it.
     */
    private function instructions(): string
    {
        return "You proofread a reply that a customer service agent of a giftware wholesaler and retailer wrote to one customer, before it is sent by chat, WhatsApp or email.\n\n"
            ."Improve only: grammar, spelling, punctuation, clarity, sentence structure and natural fluency. Keep it professional without making it formal or stiff.\n\n"
            ."Never change:\n"
            ."- the language: answer in the language the message is written in, even if it is mixed or not English;\n"
            ."- the meaning: keep every fact, answer, promise, request, apology and question; add nothing and leave nothing out;\n"
            ."- the agent's voice and warmth, greeting and sign-off;\n"
            ."- names, order numbers, product codes, prices, quantities, dates and every other number, written exactly as they are;\n"
            ."- placeholders like ⟦1⟧: copy each one exactly once, where it belongs;\n"
            ."- the formatting: keep the line breaks, lists and the *bold*, _italic_, ~strike~ and `code` marks the agent used, and add no new ones, no emoji and no headings.\n\n"
            ."Keep the length close to the original. A chat or WhatsApp message stays short and conversational. If the message already reads well, return it unchanged.\n\n"
            .'Answer only with a JSON object: {"text": "<the polished message>"}.';
    }
}
