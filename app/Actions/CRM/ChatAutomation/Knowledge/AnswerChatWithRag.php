<?php

namespace App\Actions\CRM\ChatAutomation\Knowledge;

use App\Actions\Helpers\AI\AskToAi;
use App\Models\Chat\ChatAutomation;
use App\Models\Chat\ChatKnowledgeChunk;
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

        if ($this->isConversational($question)) {
            $answer = $this->composeConversationalAnswer($persona, $question, $history);
            if ($answer !== null && trim($answer) !== '') {
                return ['answered' => true, 'text' => $this->stripMarkdown($answer), 'chunk_ids' => []];
            }
        }

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

        [$noInfo, $answer] = $this->detachNoInfoMarker($answer);

        $answer = $this->stripMarkdown($answer);

        if ($noInfo || ($answered && $this->isFallback($answer, $fallback))) {
            $answered = false;
        }

        if (trim($answer) === '') {
            return ['answered' => false, 'text' => $fallback, 'chunk_ids' => []];
        }

        if ($answered) {
            $answer = $this->appendSourceLink($answer, $confident);
        }

        return [
            'answered'  => $answered,
            'text'      => $answer,
            'chunk_ids' => $answered ? $confident->pluck('id')->all() : [],
        ];
    }

    /**
     * When retrieval returns a related-but-different page, the model deflects in the
     * customer's own language, which no exact-string check can catch. It flags those
     * non-answers with a [[NOINFO]] token so we can drop the deflection's source link
     * and route the flow down its "not found" branch.
     *
     * @return array{0: bool, 1: string}
     */
    private function detachNoInfoMarker(string $answer): array
    {
        $cleaned = preg_replace('/\[+\s*NOINFO\s*\]+/i', '', $answer) ?? $answer;

        return [$cleaned !== $answer, trim($cleaned)];
    }

    /**
     * Guarantee that an answer grounded in a crawled page carries its source link,
     * so the customer can always click through for the full detail, even when the
     * model forgets to include the URL itself.
     *
     * @param  \Illuminate\Support\Collection<int, ChatKnowledgeChunk>  $chunks
     */
    private function appendSourceLink(string $answer, \Illuminate\Support\Collection $chunks): string
    {
        $url = $this->primarySourceUrl($chunks);

        if ($url === '' || str_contains($answer, $url)) {
            return $answer;
        }

        return $answer."\n\n".__('Source:').' '.$url;
    }

    /**
     * The most relevant chunk drives the answer, so its page is the source to cite.
     *
     * @param  \Illuminate\Support\Collection<int, ChatKnowledgeChunk>  $chunks
     */
    private function primarySourceUrl(\Illuminate\Support\Collection $chunks): string
    {
        foreach ($chunks as $chunk) {
            $url  = (string) ($chunk->metadata['source_url'] ?? '');
            $name = (string) ($chunk->metadata['source_name'] ?? '');

            if ($url === '' && (str_starts_with($name, 'http://') || str_starts_with($name, 'https://'))) {
                $url = $name;
            }

            if ($url !== '') {
                return $url;
            }
        }

        return '';
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
                $name    = (string) ($chunk->metadata['source_name'] ?? '');
                $url     = (string) ($chunk->metadata['source_url'] ?? '');

                if ($url === '' && (str_starts_with($name, 'http://') || str_starts_with($name, 'https://'))) {
                    $url = $name;
                }

                if ($url !== '') {
                    return "SOURCE_URL: {$url}\n{$content}";
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

    private function isConversational(string $question): bool
    {
        $normalized = strtolower(trim((string) preg_replace('/[^a-z\s]/i', '', $question)));

        $patterns = [
            '/^(hi|hello|hey|hiya|howdy|greetings|good\s*(morning|afternoon|evening|day|night))\b/',
            '/^(thanks|thank you|thx|ty|cheers|many thanks)\b/',
            '/^(bye|goodbye|see you|cya|take care)\b/',
            '/^(ok|okay|alright|sure|got it|great|nice|cool|awesome|perfect|sounds good)\b/',
            '/^(yes|no|yep|nope|yeah|nah)\s*$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $history
     */
    private function composeConversationalAnswer(string $persona, string $question, array $history): ?string
    {
        $system = implode(' ', array_filter([
            'You are a friendly customer support assistant. Always reply in the SAME language as the customer\'s most recent message (the CUSTOMER line below), regardless of the language used earlier in the conversation.',
            'The customer has sent a short conversational message (like a greeting, thank-you, or farewell).',
            'Respond naturally and warmly in one or two short sentences. Do NOT mention knowledge bases, information availability, or topics you cover.',
            'Write in plain text only. No Markdown.',
            $persona,
        ]));

        $conversation = $this->renderHistory($history);

        $prompt = $system
            .($conversation !== '' ? "\n\nCONVERSATION SO FAR:\n".$conversation : '')
            ."\n\nCUSTOMER: ".$question;

        try {
            $answer = AskToAi::run($prompt);
            if (is_string($answer) && trim($answer) !== '') {
                return $answer;
            }

            $model = config('llmdriver.drivers.ollama.models.chat_output_model')
                ?? config('ollama-laravel.model');

            $response = Ollama::model($model)->prompt($prompt)->stream(false)->ask();

            return $response['response'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('Chat conversational answer failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $history
     */
    private function composeAnswer(string $persona, string $context, string $question, array $history, bool $confident): ?string
    {
        $rules = $confident
            ? [
                'Answer ONLY using the KNOWLEDGE below. Do not invent facts, numbers, prices, steps, links, or images that are not in the KNOWLEDGE.',
                'The KNOWLEDGE is retrieved by similarity and usually contains several blocks about DIFFERENT topics; most of them may be unrelated to the question. Judge relevance by MEANING, not exact wording: a block answers the question even when the customer used different words, synonyms, shorthand, or made typos (for example "how do you handle damaged" matches "How do you handle damaged or faulty items?"). If at least one block genuinely answers the question, use it to answer fully and simply ignore the unrelated blocks.',
                'Only when NONE of the blocks actually answers the question should you begin your reply with the exact token [[NOINFO]] and then, in the customer\'s language, say you do not have information about that specific topic yet. Never adapt or rename steps from a genuinely different subject to fake an answer, and never share a link that is not about the question.',
                'Match the shape of your answer to what the customer actually asked. If they only want a link, reply with one short sentence plus the exact link. If it is a quick factual question, answer to the point in one or two sentences. But when they ask how to do something, or ask for step-by-step, give the full ordered steps from the KNOWLEDGE, do not hold back or deflect them to a link instead.',
                'When your answer is based on a KNOWLEDGE block that starts with "SOURCE_URL:", always include that exact URL in your reply so the customer can click through for more detail. Never invent or alter a URL, and only use a SOURCE_URL that is relevant to the question.',
                'KNOWLEDGE may include images written as "[Image: description - URL]" or as plain image URLs. The chat renders an image automatically whenever you include its URL, so to SHOW an image you simply put its exact image URL on its own line at the right place in your answer. Never say you cannot show or send images, and never replace an available image URL with a link to the article. When the customer asks for steps with images, place the relevant image URL on its own line right after the step it illustrates. Never invent or alter an image URL.',
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
            ['You are a friendly customer support assistant. Always reply in the SAME language as the customer\'s most recent message (the QUESTION below), regardless of the language used earlier in the conversation. Be warm, natural, and concise.'],
            $rules,
            [
                'Write in plain text only. Do not use Markdown: no asterisks for bold or bullets, no "#" headings, no backticks.',
                'Use numbered lines "1. ", "2. ", "3. " only when you are actually giving step-by-step instructions, and keep each step short. For links or quick answers, reply naturally without forcing a list.',
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
