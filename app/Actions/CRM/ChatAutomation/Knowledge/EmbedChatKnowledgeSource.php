<?php

namespace App\Actions\CRM\ChatAutomation\Knowledge;

use App\Actions\Helpers\AI\TextChunker;
use App\Enums\CRM\Livechat\ChatKnowledgeSourceStatusEnum;
use App\Models\CRM\Livechat\ChatKnowledgeChunk;
use App\Models\CRM\Livechat\ChatKnowledgeSource;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsJob;

class EmbedChatKnowledgeSource
{
    use AsAction;
    use AsJob;

    public function handle(ChatKnowledgeSource $source): void
    {
        $source->chunks()->delete();

        $content = trim((string) $source->content);
        if ($content === '') {
            $source->update([
                'status' => ChatKnowledgeSourceStatusEnum::READY,
                'tokens' => 0,
            ]);

            return;
        }

        try {
            $sections = TextChunker::handle($content, (int) config('llmdriver.chunking.default_size', 600));

            foreach ($sections as $sectionNumber => $sectionContent) {
                $embedding = GenerateChatEmbedding::run($sectionContent);

                ChatKnowledgeChunk::create([
                    'chat_automation_id'       => $source->chat_automation_id,
                    'chat_knowledge_source_id' => $source->id,
                    'knowledge_node_id'        => $source->knowledge_node_id,
                    'guid'                     => md5($sectionContent),
                    'section_number'           => $sectionNumber,
                    'content'                  => $sectionContent,
                    'metadata'                 => ['source_name' => $source->name],
                    $embedding['column']       => $embedding['vector'],
                ]);
            }

            $source->update([
                'status' => ChatKnowledgeSourceStatusEnum::READY,
                'tokens' => str_word_count($content),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Chat knowledge embedding failed', [
                'chat_knowledge_source_id' => $source->id,
                'error'                    => $e->getMessage(),
            ]);

            $source->update(['status' => ChatKnowledgeSourceStatusEnum::FAILED]);
        }
    }

    public function asJob(ChatKnowledgeSource $source): void
    {
        $this->handle($source);
    }
}
