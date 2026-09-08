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
            'q'    => ['sometimes', 'nullable', 'string', 'max:64'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{data: array<int, array{id: string, preview: string, url: string, width: int, height: int}>, next: int|null}
     */
    public function handle(?string $q, ?int $page): array
    {
        $key = config('services.klipy.key');
        if (!$key) {
            return ['data' => [], 'next' => null];
        }

        $page ??= 1;

        return Cache::remember("klipy-search:{$q}:{$page}", $q === '' ? now()->addDay() : now()->addHours(6), function () use ($key, $q, $page) {
            try {
                $endpoint = $q ? 'https://api.klipy.com/api/v1/{key}/gifs/search' : 'https://api.klipy.com/api/v1/{key}/gifs/trending';
                $endpoint = str_replace('{key}', $key, $endpoint);

                $response = Http::timeout(5)->get($endpoint, [
                    'q'        => $q,
                    'page'     => $page,
                    'per_page' => 24,
                    'rating'   => 'pg-13',
                    'locale'   => 'en',
                ]);

                $json = $response->json() ?? [];
            } catch (\Throwable) {
                return ['data' => [], 'next' => null];
            }

            $data = collect($json['data']['data'] ?? [])->map(function (array $item) {
                $files = $item['file'] ?? $item['files'] ?? [];
                $previewData = $this->pick($files, ['sm', 'xs', 'md', 'hd'], ['webp', 'gif']);
                $urlData = $this->pick($files, ['md', 'hd', 'sm'], ['gif', 'webp']);

                if (!$urlData) {
                    return null;
                }

                return [
                    'id'      => $item['id'] ?? $item['slug'] ?? $urlData['url'],
                    'preview' => $previewData['url'] ?? null,
                    'url'     => $urlData['url'],
                    'width'   => $previewData['width'] ?? 0,
                    'height'  => $previewData['height'] ?? 0,
                ];
            })->filter()->values()->all();

            $next = ($json['data']['has_next'] ?? false) ? $page + 1 : null;

            return ['data' => $data, 'next' => $next];
        });
    }

    private function pick(array $files, array $sizes, array $formats): ?array
    {
        foreach ($sizes as $size) {
            if (!isset($files[$size])) {
                continue;
            }
            foreach ($formats as $format) {
                if (isset($files[$size][$format]['url'])) {
                    return [
                        'url' => $files[$size][$format]['url'],
                        'width' => $files[$size][$format]['width'] ?? 0,
                        'height' => $files[$size][$format]['height'] ?? 0,
                    ];
                }
            }
        }
        return null;
    }

    public function asController(ActionRequest $request): array
    {
        return $this->handle($request->get('q'), $request->get('page') ? (int) $request->get('page') : null);
    }
}
