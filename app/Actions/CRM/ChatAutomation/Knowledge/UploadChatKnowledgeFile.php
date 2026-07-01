<?php

namespace App\Actions\CRM\ChatAutomation\Knowledge;

use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatKnowledgeSourceStatusEnum;
use App\Enums\CRM\Livechat\ChatKnowledgeSourceTypeEnum;
use App\Models\Chat\ChatAutomation;
use App\Models\Chat\ChatKnowledgeSource;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class UploadChatKnowledgeFile extends OrgAction
{
    private const READABLE_EXTENSIONS = ['txt', 'md', 'markdown', 'csv', 'json', 'jsonl', 'html', 'htm'];

    public function handle(ChatAutomation $chatAutomation, array $modelData): ChatKnowledgeSource
    {
        /** @var UploadedFile $file */
        $file      = $modelData['file'];
        $extension = strtolower($file->getClientOriginalExtension());

        $isReadable = in_array($extension, self::READABLE_EXTENSIONS, true);
        $content    = $isReadable ? $this->extractText($file, $extension) : null;
        $title      = trim((string) ($modelData['title'] ?? ''));

        $source = $chatAutomation->knowledgeSources()->updateOrCreate(
            ['source_id' => $modelData['source_id']],
            [
                'knowledge_node_id' => $modelData['knowledge_node_id'],
                'type'              => ChatKnowledgeSourceTypeEnum::FILE,
                'name'              => $file->getClientOriginalName(),
                'title'             => $title !== '' ? $title : null,
                'content'           => $content,
                'content_hash'      => $content ? md5($content) : null,
                'status'            => $isReadable
                    ? ChatKnowledgeSourceStatusEnum::PENDING
                    : ChatKnowledgeSourceStatusEnum::UNSUPPORTED,
            ]
        );

        $source->addMedia($file)
            ->withProperties([
                'group_id' => $chatAutomation->shop?->group_id,
                'type'     => 'attachment',
                'ulid'     => (string) Str::ulid(),
            ])
            ->toMediaCollection('knowledge_file');

        if ($isReadable) {
            EmbedChatKnowledgeSource::dispatch($source);
        }

        return $source;
    }

    private function extractText(UploadedFile $file, string $extension): string
    {
        $raw = mb_scrub((string) file_get_contents($file->getRealPath()), 'UTF-8');
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

        if (in_array($extension, ['html', 'htm'], true)) {
            $raw = strip_tags($raw);
        } elseif ($extension === 'jsonl') {
            $raw = $this->extractConversations(preg_split('/\R/', $raw) ?: []);
        } elseif ($extension === 'json') {
            $raw = $this->extractJson($raw);
        }

        return trim(Str::of($raw)->replaceMatches('/\R{3,}/', "\n\n"));
    }

    /**
     * Exported chat history is JSON, where embedding the raw braces and keys would
     * pollute the vectors. Decode each conversation into readable "Customer:"/"Agent:"
     * turns so the knowledge stays clean and searchable.
     *
     * @param  array<int, string>  $lines
     */
    private function extractConversations(array $lines): string
    {
        $conversations = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $record = json_decode($line, true);

            if (! is_array($record)) {
                $conversations[] = $line;

                continue;
            }

            $text = $this->formatConversation($record);

            if ($text !== '') {
                $conversations[] = $text;
            }
        }

        return implode("\n\n---\n\n", $conversations);
    }

    private function extractJson(string $raw): string
    {
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return $raw;
        }

        $records = array_is_list($decoded) ? $decoded : [$decoded];

        $conversations = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $text = $this->formatConversation($record);

            if ($text !== '') {
                $conversations[] = $text;
            }
        }

        return $conversations === [] ? $raw : implode("\n\n---\n\n", $conversations);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function formatConversation(array $record): string
    {
        $messages = $record['messages'] ?? null;

        if (! is_array($messages)) {
            $messages = (isset($record['role']) && isset($record['content'])) ? [$record] : [];
        }

        if ($messages === []) {
            return '';
        }

        $lines    = [];
        $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
        $summary  = trim((string) ($metadata['summary'] ?? ''));

        if ($summary !== '') {
            $lines[] = 'Summary: '.$summary;
        }

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $role    = (string) ($message['role'] ?? '');
            $content = trim((string) ($message['content'] ?? ''));

            if ($content === '' || $role === 'system') {
                continue;
            }

            $speaker = $role === 'assistant' ? 'Agent' : 'Customer';
            $lines[] = $speaker.': '.$content;
        }

        return count($lines) > ($summary !== '' ? 1 : 0) ? implode("\n", $lines) : '';
    }

    public function rules(): array
    {
        return [
            'file'              => ['required', 'file', 'max:10240'],
            'title'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'knowledge_node_id' => ['required', 'string'],
            'source_id'         => ['required', 'string'],
        ];
    }

    public function asController(Organisation $organisation, ChatAutomation $chatAutomation, ActionRequest $request): ChatKnowledgeSource
    {
        $this->initialisation($organisation, $request);

        return $this->handle($chatAutomation, $this->validatedData);
    }
}
