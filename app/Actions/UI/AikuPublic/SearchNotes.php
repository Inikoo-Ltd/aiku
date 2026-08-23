<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\AikuPublic;

use App\Actions\Search\WithTypesenseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsController;
use Throwable;

class SearchNotes
{
    use AsController;
    use WithTypesenseApi;

    public function handle(string $query): array
    {
        if (mb_strlen($query) < 2) {
            return ['hits' => [], 'found' => 0, 'engine' => 'fallback'];
        }

        try {
            return $this->searchTypesense($query);
        } catch (Throwable) {
            return $this->searchFallback($query);
        }
    }

    protected function searchTypesense(string $query): array
    {
        $response = $this->typesenseClient()->get(
            $this->typesenseUrl().'/collections/'.IndexNotesInTypesense::COLLECTION.'/documents/search',
            [
                'q'             => $query,
                'query_by'      => 'title,summary,body,tags',
                'highlight_fields' => 'title,summary',
                'prefix'        => 'true',
                'per_page'      => 8,
            ]
        );

        if (!$response->successful()) {
            return $this->searchFallback($query);
        }

        $body = $response->json();
        $hits = collect($body['hits'] ?? [])->map(function (array $hit) {
            $document = $hit['document'];
            $highlights = collect($hit['highlights'] ?? [])->keyBy('field');

            return [
                'slug'      => $document['slug'],
                'title'     => $document['title'],
                'summary'   => $document['summary'],
                'tags'      => $document['tags'],
                'url'       => route('aiku-public.blog.show', $document['slug']),
                'highlight' => [
                    'title'   => $highlights->get('title')['snippet'] ?? $document['title'],
                    'summary' => $highlights->get('summary')['snippet'] ?? $document['summary'],
                ],
            ];
        })->all();

        return ['hits' => $hits, 'found' => $body['found'] ?? count($hits), 'engine' => 'typesense'];
    }

    protected function searchFallback(string $query): array
    {
        $needle = mb_strtolower($query);
        $hits = BlogPosts::all()
            ->filter(fn (array $post) => str_contains(mb_strtolower($post['title'].' '.$post['summary'].' '.$post['body']), $needle))
            ->take(8)
            ->map(fn (array $post) => [
                'slug'      => $post['slug'],
                'title'     => $post['title'],
                'summary'   => $post['summary'],
                'tags'      => $post['tags'],
                'url'       => route('aiku-public.blog.show', $post['slug']),
                'highlight' => ['title' => $post['title'], 'summary' => $post['summary']],
            ])
            ->values()
            ->all();

        return ['hits' => $hits, 'found' => count($hits), 'engine' => 'fallback'];
    }

    public function asController(Request $request): JsonResponse
    {
        $result = $this->handle(trim((string) $request->query('q', '')));

        return response()->json($result)->header('Cache-Control', 'public, max-age=60');
    }
}
