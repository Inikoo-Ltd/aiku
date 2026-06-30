<?php

namespace App\Actions\CRM\ChatAutomation\Knowledge;

use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatKnowledgeSourceStatusEnum;
use App\Enums\CRM\Livechat\ChatKnowledgeSourceTypeEnum;
use App\Models\CRM\Livechat\ChatAutomation;
use App\Models\CRM\Livechat\ChatKnowledgeSource;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class FetchChatKnowledgeUrl extends OrgAction
{
    public function handle(ChatAutomation $chatAutomation, array $modelData): ChatKnowledgeSource
    {
        $url       = $modelData['url'];
        $crawl     = (bool) ($modelData['crawl'] ?? true);
        $maxPages  = (int) ($modelData['max_pages'] ?? 50);
        $title     = trim((string) ($modelData['title'] ?? ''));

        $source = $chatAutomation->knowledgeSources()->updateOrCreate(
            ['source_id' => $modelData['source_id']],
            [
                'knowledge_node_id' => $modelData['knowledge_node_id'],
                'type'              => ChatKnowledgeSourceTypeEnum::URL,
                'name'              => $url,
                'title'             => $title !== '' ? $title : null,
                'status'            => ChatKnowledgeSourceStatusEnum::PENDING,
            ]
        );

        CrawlChatKnowledgeUrl::dispatch($source, $url, $crawl, $maxPages);

        return $source;
    }

    public function rules(): array
    {
        return [
            'url'               => ['required', 'url', 'max:2048'],
            'title'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'knowledge_node_id' => ['required', 'string'],
            'source_id'         => ['required', 'string'],
            'crawl'             => ['sometimes', 'boolean'],
            'max_pages'         => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }

    public function asController(Organisation $organisation, ChatAutomation $chatAutomation, ActionRequest $request): ChatKnowledgeSource
    {
        $this->initialisation($organisation, $request);

        return $this->handle($chatAutomation, $this->validatedData);
    }
}
