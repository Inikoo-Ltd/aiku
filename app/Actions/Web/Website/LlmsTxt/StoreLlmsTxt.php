<?php

namespace App\Actions\Web\Website\LlmsTxt;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Web\Website;
use App\Models\Web\WebsiteLlmsTxt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class StoreLlmsTxt extends OrgAction
{
    use WithActionUpdate;

    private Website $website;

    public function handle(Website $website, array $modelData, ?UploadedFile $file = null): WebsiteLlmsTxt
    {
        WebsiteLlmsTxt::where('website_id', $website->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $content = null;
        $path = '';
        $fileSize = 0;
        $checksum = null;
        $filename = null;

        if ($file) {
            $content = $file->get();
            $filename = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $checksum = md5($content);

            $storagePath = "llms/{$website->group_id}/websites/{$website->id}";
            $storedFilename = 'llms_' . now()->format('Y-m-d_His') . '.txt';
            Storage::disk('media')->put("{$storagePath}/{$storedFilename}", $content);
            $path = "{$storagePath}/{$storedFilename}";
        }

        $llmsTxt = WebsiteLlmsTxt::create([
            'group_id'        => $website->group_id,
            'organisation_id' => $website->organisation_id,
            'website_id'      => $website->id,
            'filename'        => $filename,
            'path'            => $path,
            'file_size'       => $fileSize,
            'content'         => $content,
            'checksum'        => $checksum,
            'is_active'       => true,
            'use_fallback'    => $modelData['use_fallback'] ?? true,
            'uploaded_by'     => request()->user()?->id,
            'uploaded_at'     => now(),
        ]);

        $website->update([
            'settings' => array_merge($website->settings ?? [], [
                'llms_txt' => [
                    'filename'     => $filename,
                    'uploaded_at'  => now()->toISOString(),
                    'file_size'    => $fileSize,
                    'use_fallback' => $modelData['use_fallback'] ?? true,
                ]
            ])
        ]);

        return $llmsTxt;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'web-admin.' . $this->organisation->id,
            'websites.edit'
        ]);
    }

    public function rules(): array
    {
        return [
            'llms_txt' => [
                'sometimes',
                'nullable',
                File::types(['txt'])
                    ->max(50)
            ],
            'use_fallback' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Website $website, ActionRequest $request): WebsiteLlmsTxt
    {
        $this->website = $website;
        $this->initialisationFromShop($website->shop, $request);

        $file = $request->file('llms_txt');

        return $this->handle($website, $this->validatedData, $file);
    }

    public function action(Website $website, array $modelData, ?UploadedFile $file = null): WebsiteLlmsTxt
    {
        $this->asAction = true;
        $this->website = $website;
        $this->initialisation($website->organisation, $modelData);

        return $this->handle($website, $this->validatedData, $file);
    }

    public function jsonResponse(WebsiteLlmsTxt $llmsTxt): array
    {
        return [
            'success'  => true,
            'message'  => __('LLMs.txt uploaded successfully'),
            'filename' => $llmsTxt->filename,
            'uploaded_at' => $llmsTxt->uploaded_at?->toISOString(),
        ];
    }
}
