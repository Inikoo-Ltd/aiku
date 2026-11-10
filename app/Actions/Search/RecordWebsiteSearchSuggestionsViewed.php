<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The storefront beacons once per search when the Keep exploring block first comes into view. The
 * first call sets the time, after the response, in one conditional update.
 */
class RecordWebsiteSearchSuggestionsViewed
{
    use AsAction;

    public function handle(string $ulid): void
    {
        DB::table('website_search_logs')->where('ulid', $ulid)->whereNull('suggestions_viewed_at')->update(['suggestions_viewed_at' => now()]);
    }

    public function rules(): array
    {
        return [
            'ulid' => ['required', 'string', 'size:26'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $ulid = $request->validated('ulid');
        logAfterResponse(fn () => $this->handle($ulid));

        return ['ok' => true];
    }
}
