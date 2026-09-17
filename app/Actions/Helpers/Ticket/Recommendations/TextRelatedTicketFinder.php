<?php

/*
 * Author Louis Perez
 * Created on 17-09-2026-13h-23m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket\Recommendations;

use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;

class TextRelatedTicketFinder implements RelatedTicketFinder
{
    private const int MAX_TERMS = 12;

    private const array STOP_WORDS = [
        'the', 'and', 'for', 'with', 'this', 'that', 'from', 'have', 'not', 'are', 'was', 'but', 'can', 'you', 'our',
        'when', 'what', 'why', 'how', 'does', 'into', 'there', 'their', 'has', 'had', 'been', 'will', 'would', 'should',
        'could', 'about', 'after', 'before', 'just', 'also', 'only', 'some', 'any', 'all', 'get', 'got', 'its', 'please',
    ];

    /**
     * @return Collection<int, Ticket>
     */
    public function find(Ticket $ticket, User $viewer, int $limit = 5): Collection
    {
        $terms = array_slice(self::words($ticket->subject.' '.mb_substr((string) $ticket->description, 0, 500)), 0, self::MAX_TERMS);

        if ($terms === []) {
            return collect();
        }

        $tsQuery = implode(' | ', $terms);

        return Ticket::query()
            ->where('tickets.group_id', $ticket->group_id)
            ->visibleTo($viewer)
            ->whereKeyNot($ticket->id)
            ->whereRaw("tickets.search_vector @@ to_tsquery('english', ?)", [$tsQuery])
            ->select('tickets.*')
            ->selectRaw("ts_rank(tickets.search_vector, to_tsquery('english', ?)) + word_similarity(?, tickets.subject COLLATE \"C\") AS related_score", [$tsQuery, $ticket->subject])
            ->orderByDesc('related_score')
            ->limit($limit)
            ->get();
    }

    /**
     * Lowercase ascii words of three letters or more, without common filler words.
     *
     * @return array<int, string>
     */
    public static function words(string $text): array
    {
        preg_match_all('/[a-z0-9]{3,}/', mb_strtolower($text), $matches);

        return collect($matches[0])
            ->reject(fn (string $word) => in_array($word, self::STOP_WORDS, true))
            ->unique()
            ->values()
            ->all();
    }
}
