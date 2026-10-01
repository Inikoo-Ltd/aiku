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
            'website'     => [$presence, 'url', 'max:255'],
            'sells_to'    => [$presence, Rule::enum(CompetitorSellsToEnum::class)],
            'currency_id' => [$presence, 'integer', 'exists:currencies,id'],
            'search_url'  => ['sometimes', 'nullable', 'starts_with:http', 'max:2000', 'regex:/\{query\}/'],
            'feed_url'    => ['sometimes', 'nullable', 'url', 'max:2000'],
            'login_url'   => ['sometimes', 'nullable', 'url', 'max:2000'],
            'username'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'password'    => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
