<?php

namespace App\Actions\CRM\ChatAutomation\Knowledge;

use App\Actions\Helpers\AI\AskToAi;
use App\Models\CRM\Livechat\ChatAutomation;
use App\Models\CRM\Livechat\ChatKnowledgeChunk;
use Cloudstudio\Ollama\Facades\Ollama;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class AnswerChatWithRag
{
    use AsAction;

    /**
     * Answer a customer question grounded in the knowledge scoped to an Ask AI node.
     * The assistant stays factual (never inventing specifics) but is conversational:
     * when there is no confident match it still replies helpfully, pointing the
     * customer to the topics it does know. The "answered" flag drives the flow's
     * "answered" / "not found" branch.
     *
     * @param  array<string, mixed>  $aiNodeData
     * @param  array<int, array{role: string, text: string}>  $history
     * @return array{answered: bool, text: string, chunk_ids: array<int, int>}
     */
    public function handle(ChatAutomation $chatAutomation, array $aiNodeData, string $question, array $history = []): array
    {
        $knowledgeNodeIds = $aiNodeData['knowledgeNodeIds'] ?? [];
        $threshold        = (float) ($aiNodeData['threshold'] ?? 0.3);
        $maxChunks        = (int) ($aiNodeData['maxChunks'] ?? 4);
        $persona          = trim((string) ($aiNodeData['persona'] ?? ''));
        $fallback         = trim((string) ($aiNodeData['fallbackMessage'] ?? ''))
            ?: __("Sorry, I don't have that info yet.");

        $searchQuery = $this->buildSearchQuery($history, $question);

        $confident = SearchChatKnowledge::run($chatAutomation, $knowledgeNodeIds, $searchQuery, $maxChunks, $threshold);
        $answered  = $confident->isNotEmpty();

        if ($answered) {
            $context = $this->buildContext($confident);
        } else {
            $topics  = $this->availableTopics($chatAutomation, $knowledgeNodeIds);
            $context = $topics === ''
                ? __("Sorry, I don't have that info yet.")
                : "You only have documentation about these topics:\n".$topics;
        }

        $answer = $this->composeAnswer($persona, $context, $question, $history, $answered);

        if ($answer === null || trim($answer) === '') {
            return ['answered' => false, 'text' => $fallback, 'chunk_ids' => []];
        }

        $answer = $this->stripMarkdown($answer);

        if ($answered && $this->isFallback($answer, $fallback)) {
            $answered = false;
        }

        return [
            'answered'  => $answered,
            'text'      => $answer,
            'chunk_ids' => $answered ? $confident->pluck('id')->all() : [],
        ];
    }

    /**
     * Follow-ups like "give me the link" are too vague to retrieve on their own,
     * so prepend the most recent customer turn to anchor the vector search.
     *
     * @param  array<int, array{role: string, text: string}>  $history
     */
    private function buildSearchQuery(array $history, string $question): string
    {
        $lastUser = '';
        foreach (array_reverse($history) as $entry) {
            if (($entry['role'] ?? '') === 'user' && trim($entry['text'] ?? '') !== '') {
                $lastUser = trim($entry['text']);
                break;
            }
        }

        return trim($lastUser.' '.$question);
    }

    /**
     * The chat widget renders plain text, so strip any Markdown the model still
     * emits (bold/italic asterisks, headings, bullets, backticks) to keep replies tidy.
     */
    private function stripMarkdown(string $answer): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $answer) ?: [];

        $lines = array_map(function (string $line): string {
            $line = preg_replace('/^\s{0,3}#{1,6}\s*/', '', $line) ?? $line;
            $line = preg_replace('/^\s*[-*+]\s+/', '• ', $line) ?? $line;

            return $line;
        }, $lines);

        $text = implode("\n", $lines);

        $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', $text) ?? $text;
        $text = preg_replace('/\*(.+?)\*/s', '$1', $text) ?? $text;
        $text = preg_replace('/(?<![\w\/])__(.+?)__(?![\w\/])/s', '$1', $text) ?? $text;
        $text = preg_replace('/(?<![\w\/])_(.+?)_(?![\w\/])/s', '$1', $text) ?? $text;
        $text = str_replace('`', '', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Prefix each chunk with its source URL (when the source is a crawled page) so
     * the model can offer the customer a clickable tutorial link as a guide.
     *
     * @param  \Illuminate\Support\Collection<int, ChatKnowledgeChunk>  $chunks
     */
    private function buildContext(\Illuminate\Support\Collection $chunks): string
    {
        return $chunks
            ->map(function (ChatKnowledgeChunk $chunk): string {
                $content = trim((string) $chunk->content);
                $source  = (string) ($chunk->metadata['source_name'] ?? '');

                if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
                    return "SOURCE_URL: {$source}\n{$content}";
                }

                return $content;
            })
            ->implode("\n---\n");
    }

    /**
     * Distinct, human-readable topic labels for the knowledge the node can see.
     * Used only when there is no confident match, so the assistant can say what it
     * DOES cover without being fed body text it might fabricate steps or links from.
     *
     * @param  array<int, string>  $knowledgeNodeIds
     */
    private function availableTopics(ChatAutomation $chatAutomation, array $knowledgeNodeIds): string
    {
        if (empty($knowledgeNodeIds)) {
            return '';
        }

        return ChatKnowledgeChunk::query()
            ->where('chat_automation_id', $chatAutomation->id)
            ->whereIn('knowledge_node_id', $knowledgeNodeIds)
            ->get(['metadata'])
            ->map(fn (ChatKnowledgeChunk $chunk) => $this->topicLabel((string) ($chunk->metadata['source_name'] ?? '')))
            ->filter()
            ->unique()
            ->take(15)
            ->map(fn (string $label) => '- '.$label)
            ->implode("\n");
    }

    private function topicLabel(string $source): string
    {
        if ($source === '') {
            return '';
        }

        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            $path     = trim((string) parse_url($source, PHP_URL_PATH), '/');
            $segments = array_filter(explode('/', $path));
            $slug     = end($segments) ?: $path;
            $label    = ucwords(trim(str_replace('-', ' ', $slug)));

            return $label !== '' ? $label : $source;
        }

        return $source;
    }

    private function isFallback(string $answer, string $fallback): bool
    {
        $normalize = fn (string $value) => preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';

        $normalizedAnswer   = $normalize($answer);
        $normalizedFallback = $normalize($fallback);

        return $normalizedFallback !== '' && str_contains($normalizedAnswer, $normalizedFallback);
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $history
     */
    private function renderHistory(array $history): string
    {
        return collect($history)
            ->filter(fn ($entry) => trim($entry['text'] ?? '') !== '')
            ->slice(-6)
            ->map(function ($entry) {
                $speaker = ($entry['role'] ?? '') === 'user' ? 'Customer' : 'Assistant';

                return $speaker.': '.trim($entry['text']);
            })
            ->implode("\n");
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $history
     */
    private function composeAnswer(string $persona, string $context, string $question, array $history, bool $confident): ?string
    {
        $rules = $confident
            ? [
                'Answer the question using the KNOWLEDGE below.',
                'Do not invent facts, numbers, links, prices, or steps that are not in the KNOWLEDGE.',
                'If a relevant KNOWLEDGE block starts with "SOURCE_URL:", share that exact URL as the tutorial/guide link so the customer can click it. Never invent or alter a URL, and only use a SOURCE_URL that is relevant to the question.',
            ]
            : [
                'There is NO documentation for this question in the knowledge base.',
                'The KNOWLEDGE below is ONLY a list of topic titles you have docs for. It is NOT an answer.',
                'You MUST NOT answer the question, write any steps, give any instructions, or share any link or URL.',
                'Reply in one or two short sentences: politely say you do not have that information yet,',
                'then, only if the list is non-empty, mention a few of the listed topic titles you CAN help with and invite a follow-up.',
                'Absolutely never invent facts, steps, numbers, prices, or links.',
            ];

        $system = implode(' ', array_filter(array_merge(
            ['You are a friendly customer support assistant. Reply in the customer\'s language. Be warm, natural, and concise.'],
            $rules,
            [
                'Write in plain text only. Do not use Markdown: no asterisks for bold or bullets, no "#" headings, no backticks.',
                'When listing steps, put each step on its own line as "1. ", "2. ", "3. " and keep them short.',
                'Use CONVERSATION to resolve follow-up questions like "give me the link".',
                $persona,
            ],
        )));

        $conversation = $this->renderHistory($history);

        $prompt = $system
            ."\n\nKNOWLEDGE:\n".($context !== '' ? $context : '(no knowledge available yet)')
            .($conversation !== '' ? "\n\nCONVERSATION SO FAR:\n".$conversation : '')
            ."\n\nQUESTION: ".$question;

        try {
            $openAiAnswer = AskToAi::run($prompt);
            if (is_string($openAiAnswer) && trim($openAiAnswer) !== '') {
                return $openAiAnswer;
            }

            $model = config('llmdriver.drivers.ollama.models.chat_output_model')
                ?? config('ollama-laravel.model');

            $response = Ollama::model($model)
                ->prompt($prompt)
                ->stream(false)
                ->ask();

            return $response['response'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('Chat RAG answer composition failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
