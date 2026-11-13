<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor;

use App\Enums\Masters\Competitor\CompetitorSellsToEnum;
use Illuminate\Validation\Rule;

trait WithCompetitorRules
{
    public function competitorRules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'name'        => [$presence, 'string', 'max:255'],
            'website'     => [$presence, 'url:http,https', 'max:255'],
            'sells_to'    => [$presence, Rule::enum(CompetitorSellsToEnum::class)],
            'currency_id' => [$presence, 'integer', 'exists:currencies,id'],
            'language_id' => ['sometimes', 'nullable', 'integer', 'exists:languages,id'],
            'search_url'  => ['sometimes', 'nullable', 'starts_with:http', 'max:2000', 'regex:/\{query\}/'],
            'feed_url'    => ['sometimes', 'nullable', 'url:http,https', 'max:2000'],
            'price_feed_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2000'],
            'login_url'   => ['sometimes', 'nullable', 'url:http,https', 'max:2000'],
            'username'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'password'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'prices_by_hand' => ['sometimes', 'boolean'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'search_url.starts_with' => __('Search their website for any word, copy the link of the results page and put {query} where the word was, e.g. https://www.example.com/search?q={query}'),
            'search_url.regex'       => __('Search their website for any word, copy the link of the results page and put {query} where the word was, e.g. https://www.example.com/search?q={query}'),
        ];
    }
}
