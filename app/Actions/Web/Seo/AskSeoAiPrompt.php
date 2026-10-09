<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Models\Web\SeoAiAnswer;
use App\Models\Web\SeoAiPrompt;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\DataForSeoException;
use App\Services\DataForSeo\DataForSeoLocations;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Asks ChatGPT one prompt through the DataForSEO LLM Scraper's live endpoint, in the prompt's country
 * and language and with web search on, and stores the answer. Live answers come back in about 30
 * seconds; the standard queue is cheaper but took over 50 minutes for a single prompt.
 */
class AskSeoAiPrompt
{
    use AsAction;

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 300;

    public int $jobTries = 1;

    /**
     * @throws DataForSeoException
     */
    public function handle(SeoAiPrompt $prompt): ?SeoAiAnswer
    {
        $client = DataForSeoClient::make();

        if (!$client) {
            $prompt->update(['queued_at' => null]);

            return null;
        }

        $prompt->loadMissing(['shop.website', 'shop.seoCompetitors']);

        try {
            $location     = DataForSeoLocations::forLlmScraper($client, $prompt->country_code);
            $languageCode = $location ? Arr::first([$prompt->language_code, Str::before($prompt->language_code, '-')], fn (string $code) => in_array($code, $location['languages'], true)) : null;

            if (!$languageCode) {
                $prompt->update(['queued_at' => null]);

                return null;
            }

            $result = Arr::first($client->live('ai_optimization/chat_gpt/llm_scraper/live/advanced', [
                'keyword'          => str_replace(['%', '+'], ['%25', '%2B'], $prompt->prompt),
                'location_code'    => $location['location_code'],
                'language_code'    => $languageCode,
                'force_web_search' => true,
            ], $prompt->shop->website));
        } catch (DataForSeoException $e) {
            $prompt->update(['queued_at' => null]);

            throw $e;
        }

        if (!$result) {
            $prompt->update(['queued_at' => null]);

            return null;
        }

        return StoreSeoAiAnswer::make()->handle($prompt, $result);
    }
}
