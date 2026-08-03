<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Helpers\WebsiteSearchLog;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordWebsiteSearchClick
{
    use AsAction;

    public function handle(string $ulid, string $url): void
    {
        $searchLog = WebsiteSearchLog::where('ulid', $ulid)->first();
        if (!$searchLog || $searchLog->clicked_at) {
            return;
        }

        $searchLog->update([
            'clicked_url' => $url,
            'clicked_at'  => now(),
        ]);
    }

    public function rules(): array
    {
        return [
            'ulid' => ['required', 'string', 'size:26'],
            'url'  => ['required', 'string', 'max:2048'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->handle($request->validated('ulid'), $request->validated('url'));

        return ['ok' => true];
    }
}
