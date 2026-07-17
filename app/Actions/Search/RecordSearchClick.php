<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 02:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Helpers\SearchLog;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RecordSearchClick
{
    use AsAction;

    public function handle(string $ulid, string $url): void
    {
        $searchLog = SearchLog::where('ulid', $ulid)->first();
        if (!$searchLog) {
            return;
        }

        if (!$searchLog->clicked_at) {
            $searchLog->update([
                'clicked_url' => $url,
                'clicked_at'  => now(),
            ]);
        }

        $this->sendProductClickEvent($searchLog, $url);
    }

    protected function sendProductClickEvent(SearchLog $searchLog, string $url): void
    {
        if (!preg_match('/majordomo\/redirect-product\/(\d+)/', $url, $matches)) {
            return;
        }

        try {
            $node = config('scout.typesense.client-settings.nodes.0');
            Http::withHeaders(['X-TYPESENSE-API-KEY' => config('scout.typesense.client-settings.api_key')])
                ->timeout(2)
                ->post($node['protocol'].'://'.$node['host'].':'.$node['port'].'/analytics/events', [
                    'type' => 'click',
                    'name' => SetupTypesenseSearchAnalytics::PRODUCT_CLICK_EVENT,
                    'data' => [
                        'q'       => $searchLog->query,
                        'doc_id'  => (string)$matches[1],
                        'user_id' => (string)($searchLog->user_id ?? 'anonymous'),
                    ],
                ]);
        } catch (Throwable) {
        }
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
