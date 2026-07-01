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
     * Vector search scoped to the given knowledge nodes, keeping only matches whose
     * cosine similarity meets the threshold.
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

        $chunks = ChatKnowledgeChunk::query()
            ->where('chat_automation_id', $chatAutomation->id)
            ->whereIn('knowledge_node_id', $knowledgeNodeIds)
            ->whereNotNull($column)
            ->nearestNeighbors($column, $embedding['vector'], Distance::Cosine)
            ->take($topK)
            ->get();

        return $chunks
            ->filter(fn (ChatKnowledgeChunk $chunk) => (1 - (float) $chunk->neighbor_distance) >= $threshold)
            ->values();
    }
}
