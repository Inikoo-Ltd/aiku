<?php

namespace App\Actions\CRM\ChatAutomation\Knowledge;

use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatKnowledgeSourceStatusEnum;
use App\Enums\CRM\Livechat\ChatKnowledgeSourceTypeEnum;
use App\Models\CRM\Livechat\ChatAutomation;
use App\Models\CRM\Livechat\ChatKnowledgeSource;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class UploadChatKnowledgeFile extends OrgAction
{
    private const READABLE_EXTENSIONS = ['txt', 'md', 'markdown', 'csv', 'json', 'html', 'htm'];

    public function handle(ChatAutomation $chatAutomation, array $modelData): ChatKnowledgeSource
    {
        /** @var UploadedFile $file */
        $file      = $modelData['file'];
        $extension = strtolower($file->getClientOriginalExtension());

        $isReadable = in_array($extension, self::READABLE_EXTENSIONS, true);
        $content    = $isReadable ? $this->extractText($file, $extension) : null;

        $source = $chatAutomation->knowledgeSources()->updateOrCreate(
            ['source_id' => $modelData['source_id']],
            [
                'knowledge_node_id' => $modelData['knowledge_node_id'],
                'type'              => ChatKnowledgeSourceTypeEnum::FILE,
                'name'              => $file->getClientOriginalName(),
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
        $raw = (string) file_get_contents($file->getRealPath());

        if (in_array($extension, ['html', 'htm'], true)) {
            $raw = strip_tags($raw);
        }

        return trim(Str::of($raw)->replaceMatches('/\R{3,}/', "\n\n"));
    }

    public function rules(): array
    {
        return [
            'file'              => ['required', 'file', 'max:10240'],
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
