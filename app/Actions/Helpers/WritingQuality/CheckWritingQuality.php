<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\WritingQuality;

use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Editorial check asked for with the "Check writing" button (HELP-4383): the AI gateway reads the
 * text and points at sentences that sound generic, padded or machine written, each with a short
 * reason and a rewrite in the text's own language. It is advice only: nothing is changed until the
 * user accepts a suggestion in the editor, and it never says whether a text was written by AI.
 */
class CheckWritingQuality
{
    use AsAction;
    use WithAIGateway;

    public const string MODEL = 'deepseek/deepseek-v4.1-flash';

    public const array CONTEXTS = ['product_description', 'web_content', 'newsletter', 'customer_email'];

    public const array CATEGORIES = ['repetition', 'cliche', 'hype', 'structure', 'filler', 'formatting', 'tone', 'vague', 'translation', 'unsupported_claim', 'placeholder'];

    private const int MAX_CHARACTERS = 20000;

    private const int MAX_ISSUES = 15;

    private const int CHECKS_PER_TEN_MINUTES = 30;

    /**
     * @param  array<int, array{id: string, text: ?string}>  $segments
     * @return array<int, array{segment: string, quote: string, category: string, explanation: string, suggestion: string}>
     * @throws ValidationException
     */
    public function handle(array $segments, string $context, string $userLocale): array
    {
        $texts = collect($segments)
            ->mapWithKeys(fn (array $segment) => [(string) $segment['id'] => $this->plainText((string) ($segment['text'] ?? ''))])
            ->filter(fn (string $text) => $text !== '');

        if ($texts->isEmpty()) {
            return [];
        }

        if (mb_strlen($texts->implode("\n")) > self::MAX_CHARACTERS) {
            throw ValidationException::withMessages(['text' => __('This text is too long to check in one go. Check it in parts.')]);
        }

        if (!$this->aiApiKey()) {
            throw ValidationException::withMessages(['text' => __('The AI gateway is not set up. Add OPENROUTER_API_KEY to the environment.')]);
        }

        try {
            $response = $this->aiRequest()
                ->timeout(90)
                ->post('chat/completions', [
                    'model'           => $this->aiModel(self::MODEL),
                    'messages'        => [
                        ['role' => 'system', 'content' => $this->instructions($context, $userLocale)],
                        ['role' => 'user', 'content' => $texts->map(fn (string $text, string $id) => "<segment id=\"$id\">\n$text\n</segment>")->implode("\n\n")],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens'      => 4000,
                ] + ($this->usesOpenRouter() ? config('services.openrouter.model_options')[self::MODEL] ?? [] : []))
                ->throw();
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['text' => __('The AI gateway did not answer: :error', ['error' => Str::limit($e->getMessage(), 200)])]);
        }

        $content = (string) $response->json('choices.0.message.content');
        $answer  = preg_match('/\{.*\}/s', $content, $match) ? json_decode($match[0], true) : null;

        if (!is_array($answer) || !is_array($answer['issues'] ?? null)) {
            throw ValidationException::withMessages(['text' => __('The AI gateway answered without a usable check. Try again.')]);
        }

        return collect($answer['issues'])
            ->filter(fn ($issue) => is_array($issue) && is_string($issue['quote'] ?? null) && trim($issue['quote']) !== '')
            ->map(fn (array $issue) => [
                'segment'     => is_scalar($issue['segment'] ?? null) ? (string) $issue['segment'] : (string) $texts->keys()->first(),
                'quote'       => preg_replace('/\s+/u', ' ', trim($issue['quote'])),
                'category'    => in_array($issue['category'] ?? null, self::CATEGORIES, true) ? $issue['category'] : 'tone',
                'explanation' => is_string($issue['explanation'] ?? null) ? trim($issue['explanation']) : '',
                'suggestion'  => is_string($issue['suggestion'] ?? null) ? trim($issue['suggestion']) : '',
            ])
            ->filter(fn (array $issue) => $texts->has($issue['segment']) && $this->contains($texts->get($issue['segment']), $issue['quote']) && $issue['suggestion'] !== $issue['quote'])
            ->unique(fn (array $issue) => $issue['segment'].'|'.$issue['quote'])
            ->take(self::MAX_ISSUES)
            ->values()
            ->all();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() instanceof User;
    }

    public function rules(): array
    {
        return [
            'segments'        => ['required', 'array', 'min:1', 'max:100'],
            'segments.*.id'   => ['required', 'string', 'max:64'],
            'segments.*.text' => ['nullable', 'string'],
            'context'         => ['required', 'string', Rule::in(self::CONTEXTS)],
        ];
    }

    /**
     * @return array<int, array{segment: string, quote: string, category: string, explanation: string, suggestion: string}>
     * @throws ValidationException
     */
    public function asController(ActionRequest $request): array
    {
        $key = 'writing-quality:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, self::CHECKS_PER_TEN_MINUTES)) {
            throw ValidationException::withMessages(['text' => __('Too many checks in a short time. Try again in a few minutes.')]);
        }

        RateLimiter::hit($key, 600);

        return $this->handle($request->validated('segments'), $request->validated('context'), app()->getLocale());
    }

    /**
     * @param  array<int, array{segment: string, quote: string, category: string, explanation: string, suggestion: string}>  $issues
     */
    public function jsonResponse(array $issues): JsonResponse
    {
        return response()->json(['issues' => $issues]);
    }

    /**
     * One line per paragraph, list item or heading, the way the editors hold them, so a quote that
     * the editor can find never spans two of them.
     */
    public function plainText(string $html): string
    {
        $text = preg_replace('/<(br|\/p|\/li|\/h[1-6]|\/div|\/blockquote|\/td|\/tr)\b[^>]*>/i', "\n", $html);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5);

        return collect(preg_split('/\n/', str_replace("\u{00A0}", ' ', $text)))
            ->map(fn (string $line) => trim(preg_replace('/[ \t]+/', ' ', $line)))
            ->filter(fn (string $line) => $line !== '')
            ->implode("\n");
    }

    private function contains(string $text, string $quote): bool
    {
        return collect(explode("\n", $text))->contains(fn (string $line) => str_contains($line, $quote));
    }

    private function instructions(string $context, string $userLocale): string
    {
        $kind = match ($context) {
            'product_description' => 'a product or product family description on the website of a giftware wholesaler and retailer',
            'newsletter'          => 'a marketing newsletter sent to customers',
            'customer_email'      => 'an email the shop sends to customers',
            default               => 'text shown on the website of a giftware wholesaler and retailer',
        };

        return "You are the editor of $kind. Point at the places where the writing sounds generic, padded, artificial or machine written, so a person can make it plain, specific and natural.\n\n"
            ."Look for: repetition (the same words, ideas or sentence shapes again), cliche (stock phrases such as \"elevate your experience\" or \"in today's fast-paced world\" in any language), hype (piled up adjectives, superlatives, claims with no information), structure (\"not only... but also\", rhetorical contrasts, triplets for rhythm), filler (introductions, summaries and sentences that say nothing), formatting (needless bold, emoji, headings or decorative punctuation), tone (robotic, over-polished or impersonal for the reader), vague (generic wording where a concrete feature, size, material or use would help), translation (wording that sounds translated or unnatural in its language), unsupported_claim (promises or product features the text gives no basis for, to be checked by a person), placeholder (leftover placeholders such as [insert name], XXX or lorem ipsum).\n\n"
            ."Judge in context: a phrase is only a problem when it weakens this text for its reader. Good text gets few or no issues; never invent problems. Never claim or guess that the text was written by AI.\n"
            .(in_array($context, ['newsletter', 'customer_email'], true) ? "Words in {braces}, {{double braces}} or [[double brackets]] are merge fields filled in when sending: never flag or change them.\n" : '')
            ."\nThe text comes in segments. Answer only with a JSON object: {\"issues\": [{\"segment\": \"<segment id>\", \"quote\": \"...\", \"category\": \"...\", \"explanation\": \"...\", \"suggestion\": \"...\"}]}, at most ".self::MAX_ISSUES." issues, the most useful first.\n"
            ."- quote: the exact words from one line of the segment, copied character for character, short (the phrase or sentence at fault, never more than one line).\n"
            ."- category: one of ".implode(', ', self::CATEGORIES).".\n"
            ."- suggestion: the replacement for quote, in the same language as the text, keeping every fact, number, name and merge field; an empty string when the words should simply be removed.\n"
            ."- explanation: one short sentence saying why, written in the language whose code is \"$userLocale\".\n"
            .'If nothing needs changing, answer {"issues": []}.';
    }
}
