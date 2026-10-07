<?php

/*
 * Author Louis Perez
 * Created on 17-09-2026-13h-23m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket\Json;

use App\Actions\Helpers\AI\AskJev;
use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;

class GetSimilarTickets extends OrgAction
{
    private const int MAX_TERMS = 12;

    private const int LIMIT = 5;

    private const int CANDIDATES = 15;

    private const float MIN_PROBABILITY = 0.3;

    private const array STOP_WORDS = [
        'the', 'and', 'for', 'with', 'this', 'that', 'from', 'have', 'not', 'are', 'was', 'but', 'can', 'you', 'our',
        'when', 'what', 'why', 'how', 'does', 'into', 'there', 'their', 'has', 'had', 'been', 'will', 'would', 'should',
        'could', 'about', 'after', 'before', 'just', 'also', 'only', 'some', 'any', 'all', 'get', 'got', 'its', 'please',
    ];

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    /**
     * The text search shortlists tickets sharing words with this one, then Jev judges which of
     * them are related (same feature, workflow or area, or the same problem); without an answer from Jev the text ranking
     * stands. Jev's judgements are cached per ticket, the viewer's visibility is applied after; with
     * cachedOnly nothing is computed and null says there is no cached answer yet.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(Ticket $ticket, User $viewer, bool $refresh = false, bool $cachedOnly = false): ?array
    {
        $cacheKey = 'ticket-similar:'.$ticket->id.':'.$ticket->updated_at?->timestamp;
        $ranking  = $refresh ? null : Cache::get($cacheKey);

        if ($ranking === null && $cachedOnly) {
            return null;
        }

        if ($ranking === null) {
            [$ranking, $isJudgedByJev] = $this->ranking($ticket);
            if ($isJudgedByJev) {
                Cache::put($cacheKey, $ranking, now()->addDay());
            }
        }

        return $this->present($ranking, $viewer);
    }

    /**
     * The ranked tickets the viewer may see, as shown in the similar tickets list.
     *
     * @param  array<int, float>  $ranking
     * @return array<int, array<string, mixed>>
     */
    public function present(array $ranking, User $viewer): array
    {
        if ($ranking === []) {
            return [];
        }

        $visible = Ticket::query()
            ->whereIn('id', array_keys($ranking))
            ->visibleTo($viewer)
            ->get()
            ->keyBy('id');

        return collect($ranking)
            ->keys()
            ->map(fn (int $id) => $visible->get($id))
            ->filter()
            ->take(self::LIMIT)
            ->map(fn (Ticket $similar) => [
                'id'           => $similar->id,
                'reference'    => $similar->reference,
                'subject'      => $similar->subject,
                'status_label' => TicketStatusEnum::labels()[$similar->status->value],
                'status_icon'  => TicketStatusEnum::stateIcon()[$similar->status->value],
                'type_icon'    => $similar->type?->icon(),
            ])
            ->values()
            ->all();
    }

    /**
     * Candidate ids with their score, most similar first, and whether Jev judged them.
     *
     * @return array{0: array<int, float>, 1: bool}
     */
    public function ranking(Ticket $ticket): array
    {
        return $this->rankText($ticket->group_id, (string) $ticket->subject, (string) $ticket->description, $ticket->id);
    }

    /**
     * Same ranking for a subject and description that may not be a ticket yet.
     *
     * @return array{0: array<int, float>, 1: bool}
     */
    public function rankText(int $groupId, string $subject, string $description, ?int $excludeTicketId = null): array
    {
        $candidates = $this->textCandidates($groupId, $subject, $description, $excludeTicketId);

        if ($candidates->isEmpty()) {
            return [[], true];
        }

        $judgements = $this->judgeWithJev($subject, $description, $candidates);

        if ($judgements === null) {
            return [$candidates->mapWithKeys(fn (Ticket $candidate) => [$candidate->id => (float) $candidate->similarity_score])->all(), false];
        }

        return [
            collect($judgements)
                ->filter(fn (float $probability) => $probability >= self::MIN_PROBABILITY)
                ->sortDesc()
                ->all(),
            true,
        ];
    }

    /**
     * @return Collection<int, Ticket>
     */
    private function textCandidates(int $groupId, string $subject, string $description, ?int $excludeTicketId): Collection
    {
        $terms = array_slice(self::words($subject.' '.mb_substr($description, 0, 500)), 0, self::MAX_TERMS);

        if ($terms === []) {
            return collect();
        }

        $tsQuery = implode(' | ', $terms);

        return Ticket::query()
            ->where('tickets.group_id', $groupId)
            ->when($excludeTicketId, fn ($query) => $query->whereKeyNot($excludeTicketId))
            ->whereRaw("tickets.search_vector @@ to_tsquery('english', ?)", [$tsQuery])
            ->select('tickets.*')
            ->selectRaw("ts_rank(tickets.search_vector, to_tsquery('english', ?)) + word_similarity(?, tickets.subject COLLATE \"C\") AS similarity_score", [$tsQuery, $subject])
            ->orderByDesc('similarity_score')
            ->limit(self::CANDIDATES)
            ->get();
    }

    /**
     * One Jev call with a yes/no question per candidate.
     *
     * @param  Collection<int, Ticket>  $candidates
     * @return array<int, float>|null probability per candidate id, null when Jev did not answer
     */
    private function judgeWithJev(string $subject, string $description, Collection $candidates): ?array
    {
        $describeText = fn (string $title, string $body) => trim($title."\n".mb_substr(trim(strip_tags($body)), 0, 600));
        $describe     = fn (Ticket $other) => $describeText((string) $other->subject, (string) $other->description);

        $questions = $candidates->mapWithKeys(fn (Ticket $candidate) => ['ticket_'.$candidate->id => [
            'type'         => 'noul',
            'instructions' => "Is this other ticket related to the current ticket, so that someone working on the current one would want to know about it?\n\nOther ticket:\n".$describe($candidate),
            'criteria'     => [
                'true'  => 'it is about the same feature, the same workflow or the same area of the system, or the same problem: a duplicate, an earlier or follow-up request, or work that touches the same screens or data',
                'false' => 'it only shares a few words, a team name or a module prefix, and is about a different part of the system',
            ],
        ]])->all();

        $answers = AskJev::make()->handle(['current_ticket' => $describeText($subject, $description)], $questions);

        if ($answers === null) {
            return null;
        }

        return $candidates->mapWithKeys(function (Ticket $candidate) use ($answers) {
            $probability = Arr::get($answers, 'ticket_'.$candidate->id.'.noul');

            return is_numeric($probability) ? [$candidate->id => (float) $probability] : [];
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $similarTickets
     */
    public function jsonResponse(?array $similarTickets): JsonResponse
    {
        return response()->json($similarTickets ?? ['cached' => false]);
    }

    /**
     * Lowercase words of three letters or more, without common filler words.
     *
     * @return array<int, string>
     */
    public static function words(string $text): array
    {
        preg_match_all('/[a-z0-9]{3,}/', mb_strtolower($text), $matches);

        return Collection::make($matches[0])
            ->reject(fn (string $word) => in_array($word, self::STOP_WORDS, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function asController(Ticket $ticket, ActionRequest $request): ?array
    {
        abort_unless($ticket->isVisibleTo($request->user()), 403);
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($ticket, $request->user(), $request->boolean('refresh'), $request->boolean('cached_only'));
    }
}
