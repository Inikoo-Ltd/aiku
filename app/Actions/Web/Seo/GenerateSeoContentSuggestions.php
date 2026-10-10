<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Actions\Web\Webpage\WithWebpageSeoData;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Seo\SeoContentSuggestionStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\SysAdmin\User;
use App\Models\Web\SeoApiRequest;
use App\Models\Web\SeoContentSuggestion;
use App\Models\Web\Webpage;
use App\Services\SeoApi\SeoApiBudget;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

/**
 * Asks the AI gateway for a meta title and/or description of one webpage, from what the page shows
 * (its product or category), the Google searches it already appears for, and the length limits the
 * Site Audit checks. The result replaces any pending suggestion for the same field; nothing is
 * written to the webpage until someone accepts it.
 */
class GenerateSeoContentSuggestions
{
    use AsObject;
    use WithAIGateway;
    use WithWebpageSeoData;

    public const string MODEL = 'gpt-5.6-luna';

    private const int SEARCH_QUERIES = 10;

    private const int CONTENT_LENGTH = 1500;

    /**
     * @param  array<int, string>  $fields
     * @return Collection<int, SeoContentSuggestion>
     * @throws ValidationException
     */
    public function handle(Webpage $webpage, array $fields, string $reason, ?User $user = null): Collection
    {
        $fields = array_values(array_intersect([SeoContentSuggestion::FIELD_TITLE, SeoContentSuggestion::FIELD_DESCRIPTION], $fields));

        if ($fields === []) {
            return collect();
        }

        if (!$this->aiApiKey()) {
            throw ValidationException::withMessages(['suggestion' => __('The AI gateway is not set up. Add OPENROUTER_API_KEY to the environment.')]);
        }

        if (SeoApiBudget::isReached()) {
            throw ValidationException::withMessages(['suggestion' => __('The SEO API budget for this month (:budget USD) has been reached.', ['budget' => SeoApiBudget::monthlyBudget()])]);
        }

        $written = $this->ask($webpage, $fields);

        return collect($fields)
            ->filter(fn (string $field) => filled($written[$field] ?? null))
            ->map(function (string $field) use ($webpage, $written, $reason, $user) {
                SeoContentSuggestion::where('webpage_id', $webpage->id)
                    ->where('field', $field)
                    ->where('state', SeoContentSuggestionStateEnum::PENDING)
                    ->delete();

                return SeoContentSuggestion::create([
                    'website_id'           => $webpage->website_id,
                    'webpage_id'           => $webpage->id,
                    'field'                => $field,
                    'current_value'        => $field === SeoContentSuggestion::FIELD_TITLE ? $webpage->title : $webpage->description,
                    'suggestion'           => Str::squish($written[$field]),
                    'reason'               => $reason,
                    'state'                => SeoContentSuggestionStateEnum::PENDING,
                    'model'                => $this->aiModel(self::MODEL),
                    'requested_by_user_id' => $user?->id,
                ]);
            })
            ->values();
    }

    /**
     * The longest page title that Google still shows in full after the website's prefix. Google cuts
     * a title at about 60 characters from the start, so the suffix may be cut but the page title
     * should not be.
     */
    public function titleMaxLength(Webpage $webpage): int
    {
        $prefix = Arr::get($webpage->seo_data, 'use_title_prefix_suffix', true)
            ? (data_get($webpage->settings, 'webpage.title_prefix') ?: data_get($webpage->website->settings, 'webpage.title_prefix'))
            : null;

        return max(30, CrawlIssueTypeEnum::TITLE_MAX_LENGTH - ($prefix ? mb_strlen($prefix) + 1 : 0));
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, string>
     * @throws ValidationException
     */
    private function ask(Webpage $webpage, array $fields, int $attempt = 1): array
    {
        $startedAt = hrtime(true);
        $provider  = $this->usesOpenRouter() ? 'openrouter' : 'openai';

        try {
            $response = $this->aiRequest()
                ->timeout(90)
                ->post('chat/completions', [
                    'model'           => $this->aiModel(self::MODEL),
                    'messages'        => [
                        ['role' => 'system', 'content' => $this->instructions($webpage, $fields)],
                        ['role' => 'user', 'content' => $this->pageContext($webpage)],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens'      => 2000,
                ])
                ->throw();
        } catch (Throwable $e) {
            $this->log($webpage, $provider, false, 0, null, $startedAt, $e->getMessage());

            throw ValidationException::withMessages(['suggestion' => __('The AI gateway did not answer: :error', ['error' => Str::limit($e->getMessage(), 200)])]);
        }

        $content = (string) $response->json('choices.0.message.content');
        $written = preg_match('/\{.*\}/s', $content, $match) ? json_decode($match[0], true) : null;
        $isUsable = is_array($written) && collect($fields)->contains(fn (string $field) => is_string($written[$field] ?? null) && trim($written[$field]) !== '');

        $this->log($webpage, $provider, $isUsable, count($fields), $this->aiUsageCost($response->json('usage') ?? []), $startedAt, $isUsable ? null : 'No usable JSON in the answer: '.Str::limit($content, 300));

        if (!$isUsable) {
            if ($attempt < 2) {
                return $this->ask($webpage, $fields, $attempt + 1);
            }

            throw ValidationException::withMessages(['suggestion' => __('The AI gateway answered without a usable suggestion. Try again.')]);
        }

        return array_map(fn ($value) => is_string($value) ? trim($value, " \t\n\r\"'") : '', Arr::only($written, $fields));
    }

    /**
     * @param  array<int, string>  $fields
     */
    private function instructions(Webpage $webpage, array $fields): string
    {
        $language   = $webpage->shop?->language?->name ?? 'English';
        $titleMax   = $this->titleMaxLength($webpage);
        $fullTitle  = (string) $this->getWebpageSeoTitle($webpage);
        $hasAffixes = $fullTitle !== (string) $webpage->title;
        $rules      = [];

        if (in_array(SeoContentSuggestion::FIELD_TITLE, $fields, true)) {
            $rules[] = "\"title\": the page title, at most $titleMax characters and at least ".CrawlIssueTypeEnum::TITLE_MIN_LENGTH.'. Lead with what the page offers, in the words people search with.'
                .($hasAffixes ? " The website adds its own text around it (the full title now reads \"$fullTitle\"), so do not repeat that text, the shop or the brand name." : '');
        }

        if (in_array(SeoContentSuggestion::FIELD_DESCRIPTION, $fields, true)) {
            $rules[] = '"description": the meta description, between 120 and '.(CrawlIssueTypeEnum::META_DESCRIPTION_MAX_LENGTH - 5).' characters. Say what the page offers and give one concrete reason to click.';
        }

        return "You write the Google search snippet of one page of an online shop. Write in $language.\n\n"
            ."Return only a JSON object with these keys:\n- ".implode("\n- ", $rules)."\n\n"
            ."Use the searches the page already appears for when they fit. State only facts found in the page content: no invented prices, discounts, delivery promises or awards. "
            .'No quotation marks, no emoji, no capital letters for emphasis, no exclamation marks.';
    }

    private function pageContext(Webpage $webpage): string
    {
        $model   = $webpage->model;
        $context = [
            'Website'                  => $webpage->website->name.' ('.$webpage->website->domain.')',
            'Page type'                => $webpage->type?->value,
            'Page URL'                 => $webpage->getUrl(),
            'Current title'            => $webpage->title,
            'Current meta description' => $webpage->description,
        ];

        if ($model instanceof Product) {
            $context['Product'] = $model->name;
            $context['Product code'] = $model->code;
            $context['Product description'] = $this->plainText($model->description);
            $context['Family'] = $model->family?->name;
        } elseif ($model instanceof ProductCategory) {
            $context['Category'] = $model->name;
            $context['Category description'] = $this->plainText($model->description);
            $context['Some of its products'] = $model->getProducts()->take(8)->pluck('name')->implode(', ') ?: null;
        }

        $queries = DB::table('search_console_page_queries')
            ->where('webpage_id', $webpage->id)
            ->where('date', '>=', now()->subDays(90)->toDateString())
            ->groupBy('query')
            ->select('query')
            ->selectRaw('SUM(impressions) AS impressions')
            ->orderByDesc('impressions')
            ->limit(self::SEARCH_QUERIES)
            ->pluck('query')
            ->implode(', ');

        $context['Google searches it appears for'] = $queries ?: null;

        return collect($context)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value, $label) => "$label: $value")
            ->implode("\n");
    }

    private function plainText(?string $html): ?string
    {
        return $html ? Str::limit(Str::squish(html_entity_decode(strip_tags($html))), self::CONTENT_LENGTH) : null;
    }

    private function log(Webpage $webpage, string $provider, bool $isSuccess, int $rows, ?float $cost, int $startedAt, ?string $error = null): void
    {
        SeoApiRequest::create([
            'provider'    => $provider,
            'endpoint'    => 'content_suggestions',
            'website_id'  => $webpage->website_id,
            'is_success'  => $isSuccess,
            'rows'        => $rows,
            'duration_ms' => intdiv(hrtime(true) - $startedAt, 1_000_000),
            'cost'        => $cost,
            'error'       => $error ? Str::limit($error, 2000) : null,
        ]);
    }
}
