<?php

namespace App\Actions\CRM\ChatAutomation\Knowledge;

use App\Models\Chat\ChatAutomation;
use App\Models\Chat\ChatKnowledgeChunk;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Pgvector\Laravel\Distance;

class SearchChatKnowledge
{
    use AsAction;

    /**
     * Constant used by Reciprocal Rank Fusion; dampens the weight of lower ranks.
     */
    private const RRF_K = 60;

    /**
     * Hybrid retrieval scoped to the given knowledge nodes: semantic (vector) search
     * is fused with keyword (Postgres full-text) search via Reciprocal Rank Fusion,
     * so exact terms the embedding misses are still surfaced.
     *
     * @param  array<int, string>  $knowledgeNodeIds
     * @return Collection<int, ChatKnowledgeChunk>
     */
    public function handle(
        ChatAutomation $chatAutomation,
        array $knowledgeNodeIds,
        string $query,
        int $topK = 4,
        float $threshold = 0.3,
    ): Collection {
        if (empty($knowledgeNodeIds) || trim($query) === '') {
            return collect();
        }

        $embedding = GenerateChatEmbedding::run($query);
        $column    = $embedding['column'];
        $poolSize  = max($topK * 5, 20);

        $vectorMatches  = $this->vectorMatches($chatAutomation, $knowledgeNodeIds, $column, $embedding['vector'], $poolSize);
        $keywordRanking = $this->keywordRanking($chatAutomation, $knowledgeNodeIds, $column, $query, $poolSize);

        $scores = [];
        $this->addRrfScores($scores, $vectorMatches->keys()->all());
        $this->addRrfScores($scores, $keywordRanking);

        $qualifiedIds = collect(array_keys($scores))
            ->filter(function ($chunkId) use ($vectorMatches, $keywordRanking, $threshold) {
                if (in_array($chunkId, $keywordRanking, true)) {
                    return true;
                }

                return ($vectorMatches[$chunkId] ?? 0.0) >= $threshold;
            })
            ->sortByDesc(fn ($chunkId) => $scores[$chunkId])
            ->take($topK)
            ->values();

        if ($qualifiedIds->isEmpty()) {
            return collect();
        }

        $chunks = ChatKnowledgeChunk::query()
            ->whereIn('id', $qualifiedIds->all())
            ->get()
            ->keyBy('id');

        return $qualifiedIds
            ->map(fn ($chunkId) => $chunks->get($chunkId))
            ->filter()
            ->values();
    }

    /**
     * @param  array<int, string>  $knowledgeNodeIds
     * @return Collection<int, float>  chunk id => cosine similarity, best first
     */
    private function vectorMatches(
        ChatAutomation $chatAutomation,
        array $knowledgeNodeIds,
        string $column,
        mixed $vector,
        int $poolSize,
    ): Collection {
        return ChatKnowledgeChunk::query()
            ->where('chat_automation_id', $chatAutomation->id)
            ->whereIn('knowledge_node_id', $knowledgeNodeIds)
            ->whereNotNull($column)
            ->nearestNeighbors($column, $vector, Distance::Cosine)
            ->take($poolSize)
            ->get()
            ->mapWithKeys(fn (ChatKnowledgeChunk $chunk) => [
                $chunk->id => 1 - (float) $chunk->neighbor_distance,
            ]);
    }

    /**
     * @param  array<int, string>  $knowledgeNodeIds
     * @return array<int, int>  chunk ids ordered by keyword relevance, best first
     */
    private function keywordRanking(
        ChatAutomation $chatAutomation,
        array $knowledgeNodeIds,
        string $column,
        string $query,
        int $poolSize,
    ): array {
        return ChatKnowledgeChunk::query()
            ->where('chat_automation_id', $chatAutomation->id)
            ->whereIn('knowledge_node_id', $knowledgeNodeIds)
            ->whereNotNull($column)
            ->whereRaw("to_tsvector('simple', content) @@ websearch_to_tsquery('simple', ?)", [$query])
            ->orderByRaw("ts_rank(to_tsvector('simple', content), websearch_to_tsquery('simple', ?)) DESC", [$query])
            ->take($poolSize)
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<int, float>  $scores
     * @param  array<int, int>  $rankedIds
     */
    private function addRrfScores(array &$scores, array $rankedIds): void
    {
        foreach ($rankedIds as $rank => $chunkId) {
            $scores[$chunkId] = ($scores[$chunkId] ?? 0.0) + 1 / (self::RRF_K + $rank + 1);
        }
    }
}
