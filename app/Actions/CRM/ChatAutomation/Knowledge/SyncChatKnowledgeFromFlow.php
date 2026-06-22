<?php

namespace App\Actions\CRM\ChatAutomation\Knowledge;

use App\Enums\CRM\Livechat\ChatKnowledgeSourceStatusEnum;
use App\Enums\CRM\Livechat\ChatKnowledgeSourceTypeEnum;
use App\Models\CRM\Livechat\ChatAutomation;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncChatKnowledgeFromFlow
{
    use AsAction;

    /**
     * Reconcile persisted knowledge sources with the knowledge nodes in the flow.
     * Text sources are synced from the flow; file sources are kept (their content is
     * uploaded separately). Sources removed from the flow are deleted with their chunks.
     */
    public function handle(ChatAutomation $chatAutomation): void
    {
        $seenSourceIds = [];

        foreach ($chatAutomation->flowNodesOfType('knowledge') as $node) {
            $nodeId = $node['id'] ?? null;
            if (! $nodeId) {
                continue;
            }

            foreach (Arr::get($node, 'data.sources', []) as $source) {
                $sourceId = $source['id'] ?? null;
                if (! $sourceId) {
                    continue;
                }

                $seenSourceIds[] = $sourceId;
                $type = $source['type'] ?? ChatKnowledgeSourceTypeEnum::TEXT->value;

                if ($type === ChatKnowledgeSourceTypeEnum::TEXT->value) {
                    $this->syncTextSource($chatAutomation, $nodeId, $sourceId, $source);

                    continue;
                }

                $this->syncManagedSource($chatAutomation, $nodeId, $sourceId, $source);
            }
        }

        $chatAutomation->knowledgeSources()
            ->when($seenSourceIds, fn ($query) => $query->whereNotIn('source_id', $seenSourceIds))
            ->get()
            ->each
            ->delete();
    }

    private function syncTextSource(ChatAutomation $chatAutomation, string $nodeId, string $sourceId, array $source): void
    {
        $content = trim($source['text'] ?? '');
        $hash    = md5($nodeId.'|'.$content);

        $existing = $chatAutomation->knowledgeSources()->where('source_id', $sourceId)->first();

        if ($existing
            && $existing->content_hash === $hash
            && $existing->status === ChatKnowledgeSourceStatusEnum::READY
        ) {
            $existing->update([
                'knowledge_node_id' => $nodeId,
                'name'              => $source['name'] ?? $existing->name,
            ]);

            return;
        }

        $record = $chatAutomation->knowledgeSources()->updateOrCreate(
            ['source_id' => $sourceId],
            [
                'knowledge_node_id' => $nodeId,
                'type'              => ChatKnowledgeSourceTypeEnum::TEXT,
                'name'              => $source['name'] ?? null,
                'content'           => $content,
                'content_hash'      => $hash,
                'status'            => ChatKnowledgeSourceStatusEnum::PENDING,
            ]
        );

        EmbedChatKnowledgeSource::dispatch($record);
    }

    private function syncManagedSource(ChatAutomation $chatAutomation, string $nodeId, string $sourceId, array $source): void
    {
        $existing = $chatAutomation->knowledgeSources()->where('source_id', $sourceId)->first();
        if (! $existing) {
            return;
        }

        $existing->update([
            'knowledge_node_id' => $nodeId,
            'name'              => $source['name'] ?? $existing->name,
        ]);
    }
}
