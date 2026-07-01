<?php

namespace App\Actions\CRM\ChatAutomation\Knowledge;

use App\Actions\OrgAction;
use App\Models\Chat\ChatAutomation;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class PreviewChatRagAnswer extends OrgAction
{
    /**
     * @return array{answered: bool, text: string, chunk_ids: array<int, int>}
     */
    public function handle(ChatAutomation $chatAutomation, array $modelData): array
    {
        $aiNodeData = [
            'knowledgeNodeIds' => $modelData['knowledge_node_ids'] ?? [],
            'threshold'        => $modelData['threshold'] ?? 0.3,
            'maxChunks'        => $modelData['max_chunks'] ?? 4,
            'persona'          => $modelData['persona'] ?? '',
            'fallbackMessage'  => $modelData['fallback_message'] ?? '',
        ];

        return AnswerChatWithRag::run($chatAutomation, $aiNodeData, $modelData['question'], $modelData['history'] ?? []);
    }

    public function rules(): array
    {
        return [
            'question'            => ['required', 'string', 'max:2000'],
            'knowledge_node_ids'  => ['sometimes', 'array'],
            'knowledge_node_ids.*' => ['string'],
            'threshold'           => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'max_chunks'          => ['sometimes', 'integer', 'min:1', 'max:10'],
            'persona'             => ['sometimes', 'nullable', 'string', 'max:1000'],
            'fallback_message'    => ['sometimes', 'nullable', 'string', 'max:1000'],
            'history'             => ['sometimes', 'array', 'max:20'],
            'history.*.role'      => ['required_with:history', 'string'],
            'history.*.text'      => ['required_with:history', 'string'],
        ];
    }

    public function asController(Organisation $organisation, ChatAutomation $chatAutomation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle($chatAutomation, $this->validatedData);
    }
}
