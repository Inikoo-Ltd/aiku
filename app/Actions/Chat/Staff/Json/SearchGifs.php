<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Json;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchGifs
{
    use AsAction;

    public function rules(): array
    {
        return [
            'q'   => ['sometimes', 'nullable', 'string', 'max:64'],
            'pos' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array{data: array<int, array{id: string, preview: string, url: string, width: int, height: int}>, next: string|null}
     */
    public function handle(?string $q, ?string $pos): array
    {
        $key = config('services.tenor.key');
        if (!$key) {
            return ['data' => [], 'next' => null];
        }

        return Cache::remember("tenor-search:{$q}:{$pos}", now()->addMinutes(10), function () use ($key, $q, $pos) {
            try {
                $endpoint = $q ? 'https://tenor.googleapis.com/v2/search' : 'https://tenor.googleapis.com/v2/featured';
                $response = Http::timeout(5)->get($endpoint, [
                    'key'           => $key,
                    'client_key'    => config('services.tenor.client_key'),
                    'q'             => $q,
                    'limit'         => 24,
                    'media_filter'  => 'tinygif,gif',
                    'contentfilter' => 'medium',
                    'pos'           => $pos,
                ]);

                $json = $response->json() ?? [];
            } catch (\Throwable) {
                return ['data' => [], 'next' => null];
            }

            $data = collect($json['results'] ?? [])->map(function (array $result) {
                $tiny = $result['media_formats']['tinygif'] ?? [];

                return [
                    'id'      => $result['id'],
                    'preview' => $tiny['url'] ?? null,
                    'url'     => $result['media_formats']['gif']['url'] ?? null,
                    'width'   => $tiny['dims'][0] ?? 0,
                    'height'  => $tiny['dims'][1] ?? 0,
                ];
            })->values()->all();

            return ['data' => $data, 'next' => $json['next'] ?? null];
        });
    }

    public function asController(ActionRequest $request): array
    {
        return $this->handle($request->get('q'), $request->get('pos'));
    }
}
