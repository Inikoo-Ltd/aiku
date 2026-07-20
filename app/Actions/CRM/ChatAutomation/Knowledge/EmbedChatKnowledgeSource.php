<?php

namespace App\Actions\CRM\ChatAutomation\Knowledge;

use App\Enums\CRM\Livechat\ChatKnowledgeSourceStatusEnum;
use App\Models\Chat\ChatKnowledgeChunk;
use App\Models\Chat\ChatKnowledgeSource;
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
            $sections = $this->splitIntoChunks($content, (int) config('llmdriver.chunking.default_size', 600));
            $metadata = $this->sourceMetadata($source);
            $heading  = (string) ($metadata['source_name'] ?? '');

            foreach ($sections as $sectionNumber => $sectionContent) {
                $chunkContent = $this->withSourceHeading($heading, $sectionContent);
                $embedding    = GenerateChatEmbedding::run($chunkContent);

                ChatKnowledgeChunk::create([
                    'chat_automation_id'       => $source->chat_automation_id,
                    'chat_knowledge_source_id' => $source->id,
                    'knowledge_node_id'        => $source->knowledge_node_id,
                    'guid'                     => md5($chunkContent),
                    'section_number'           => $sectionNumber,
                    'content'                  => $chunkContent,
                    'metadata'                 => $metadata,
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

    /**
     * Build the chunk metadata. A crawled URL keeps its address in "source_url" so
     * the assistant can still offer the clickable link, while "source_name" carries
     * the human title the user gave (falling back to the URL) for topic labels.
     *
     * @return array<string, string>
     */
    private function sourceMetadata(ChatKnowledgeSource $source): array
    {
        $name = trim((string) $source->name);
        $url  = (str_starts_with($name, 'http://') || str_starts_with($name, 'https://')) ? $name : '';
        $title = trim((string) $source->title);

        $metadata = ['source_name' => $title !== '' ? $title : $name];

        if ($url !== '') {
            $metadata['source_url'] = $url;
        }

        return $metadata;
    }

    /**
     * Prepend the source title to each chunk so the title itself becomes searchable by
     * both vector and keyword retrieval. Without this, a source whose body text is thin
     * (e.g. a YouTube link) is only findable by its URL, so asking for it by name — like
     * "eBay video" — never matches. A bare URL title is skipped as it adds no keywords.
     */
    private function withSourceHeading(string $heading, string $content): string
    {
        $heading = trim($heading);

        if ($heading === '' || str_starts_with($heading, 'http://') || str_starts_with($heading, 'https://')) {
            return $content;
        }

        if (mb_stripos($content, $heading) === 0) {
            return $content;
        }

        return $heading."\n\n".$content;
    }

    /**
     * Split content into embedding chunks at natural boundaries (documents,
     * paragraphs, then sentences) instead of a fixed byte window, so a chunk never
     * ends mid-word or mid-sentence. Consecutive paragraphs are grouped up to the
     * size limit and carry a small overlap for context continuity.
     *
     * @return array<int, string>
     */
    private function splitIntoChunks(string $content, int $chunkSize): array
    {
        $chunkSize = max(120, $chunkSize);
        $chunks    = [];
        $current   = '';

        foreach ($this->splitIntoUnits($content, $chunkSize) as $unit) {
            if ($unit === '---') {
                if (trim($current) !== '') {
                    $chunks[] = trim($current);
                }
                $current = '';

                continue;
            }

            if ($current !== '' && mb_strlen($current) + mb_strlen($unit) + 2 > $chunkSize) {
                $chunks[] = trim($current);
                $current  = $this->overlapTail($current, $chunkSize);
            }

            $current = $current === '' ? $unit : $current."\n".$unit;
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return array_values(array_filter($chunks, fn (string $chunk): bool => $chunk !== ''));
    }

    /**
     * @return array<int, string>
     */
    private function splitIntoUnits(string $content, int $chunkSize): array
    {
        $units = [];

        foreach (preg_split('/\n\s*\n/', $content) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if ($paragraph === '---' || mb_strlen($paragraph) <= $chunkSize) {
                $units[] = $paragraph;

                continue;
            }

            foreach ($this->splitSentences($paragraph) as $sentence) {
                if (mb_strlen($sentence) <= $chunkSize) {
                    $units[] = $sentence;

                    continue;
                }

                foreach ($this->hardWrap($sentence, $chunkSize) as $piece) {
                    $units[] = $piece;
                }
            }
        }

        return $units;
    }

    /**
     * @return array<int, string>
     */
    private function splitSentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?。！？])\s+/u', $text) ?: [$text];

        return array_values(array_filter(array_map('trim', $parts), fn (string $part): bool => $part !== ''));
    }

    /**
     * @return array<int, string>
     */
    private function hardWrap(string $text, int $chunkSize): array
    {
        $lines = [];
        $line  = '';

        foreach (preg_split('/\s+/u', $text) ?: [] as $word) {
            if (mb_strlen($word) > $chunkSize) {
                if ($line !== '') {
                    $lines[] = $line;
                    $line    = '';
                }

                foreach (mb_str_split($word, $chunkSize) as $piece) {
                    $lines[] = $piece;
                }

                continue;
            }

            $candidate = $line === '' ? $word : $line.' '.$word;

            if (mb_strlen($candidate) > $chunkSize) {
                $lines[] = $line;
                $line    = $word;
            } else {
                $line = $candidate;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function overlapTail(string $text, int $chunkSize): string
    {
        $maxOverlap = (int) floor($chunkSize * 0.15);

        if ($maxOverlap < 1 || mb_strlen($text) <= $maxOverlap) {
            return '';
        }

        $tail     = mb_substr($text, -$maxOverlap);
        $boundary = mb_strpos($tail, ' ');

        if ($boundary !== false) {
            $tail = mb_substr($tail, $boundary + 1);
        }

        return trim($tail);
    }

    public function asJob(ChatKnowledgeSource $source): void
    {
        $this->handle($source);
    }
}
