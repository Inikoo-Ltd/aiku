<?php

/*
 * Author Louis Perez
 * Created on 17-09-2026-13h-23m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\Ticket\Recommendations\TextRelatedTicketFinder;
use App\Actions\UI\AikuPublic\BlogPosts;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Helpers\Ticket;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class SuggestTicketArticles
{
    use AsObject;

    public const string CUSTOMER_KNOWLEDGE_BASE = 'markdown/knowledge-base/customer';

    private const array FIELD_WEIGHTS = ['title' => 3, 'keywords' => 2, 'summary' => 1];

    /**
     * @return array<int, array{title: string, summary: string, url: string, source: string}>
     */
    public function handle(Ticket $ticket, int $limit = 3): array
    {
        $terms = TextRelatedTicketFinder::words($ticket->subject.' '.(string) $ticket->description);

        if ($terms === []) {
            return [];
        }

        $articles = $ticket->type === TicketTypeEnum::CUSTOMER ? $this->customerArticles() : $this->helpDocs();

        return $articles
            ->map(fn (array $article) => [...$article, 'score' => $this->score($article, $terms)])
            ->filter(fn (array $article) => $article['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn (array $article) => Arr::only($article, ['title', 'summary', 'url', 'source']))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{title: string, summary: string, keywords: string, url: string, source: string}>
     */
    public function customerArticles(): Collection
    {
        return collect(glob(resource_path(self::CUSTOMER_KNOWLEDGE_BASE.'/*.md')))
            ->reject(fn (string $path) => basename($path) === 'index.md')
            ->map(function (string $path) {
                $meta = $this->frontMatter((string) file_get_contents($path));

                return [
                    'title'    => $meta['title'] ?? basename($path, '.md'),
                    'summary'  => $meta['summary'] ?? '',
                    'keywords' => trim(($meta['keywords'] ?? '').' '.($meta['tags'] ?? '').' '.($meta['category'] ?? '')),
                    'url'      => $meta['source_url'] ?? '',
                    'source'   => 'customer',
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array{title: string, summary: string, keywords: string, url: string, source: string}>
     */
    public function helpDocs(): Collection
    {
        return BlogPosts::all('docs')
            ->map(fn (array $doc) => [
                'title'    => $doc['title'],
                'summary'  => $doc['summary'],
                'keywords' => trim(implode(' ', $doc['tags']).' '.($doc['category'] ?? '')),
                'url'      => route('aiku-public.docs.show', $doc['slug']),
                'source'   => 'help',
            ])
            ->values();
    }

    /**
     * @param array<int, string> $terms
     */
    private function score(array $article, array $terms): int
    {
        $score = 0;

        foreach (self::FIELD_WEIGHTS as $field => $weight) {
            $fieldWords = TextRelatedTicketFinder::words((string) $article[$field]);

            foreach ($terms as $term) {
                foreach ($fieldWords as $fieldWord) {
                    if ($this->wordsMatch($term, $fieldWord)) {
                        $score += $weight;

                        break;
                    }
                }
            }
        }

        return $score;
    }

    private function wordsMatch(string $term, string $word): bool
    {
        if ($term === $word) {
            return true;
        }

        return min(strlen($term), strlen($word)) >= 4 && (str_starts_with($word, $term) || str_starts_with($term, $word));
    }

    /**
     * @return array<string, string>
     */
    private function frontMatter(string $raw): array
    {
        if (!preg_match('/^---\n(.*?)\n---\n/s', $raw, $matches)) {
            return [];
        }

        return collect(explode("\n", $matches[1]))
            ->filter(fn (string $line) => str_contains($line, ':'))
            ->mapWithKeys(function (string $line) {
                [$key, $value] = array_map('trim', explode(':', $line, 2));

                return [$key => $value];
            })
            ->all();
    }
}
