<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Web\StaticWebsite\ApplyStaticWebsiteChange;
use App\Actions\Web\StaticWebsite\StageStaticWebsiteChange;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Web\StaticWebsite;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Saves a change to an info website that info-website-preview-tool prepared, exactly as previewed, after the user saw the summary, the edits and the preview and confirmed in their own words. Pass the change_id and their confirmation as request_text. Refused when the change belongs to someone else, has expired (a day), or the page or shared part changed after the preview: then preview again. Every page and shared part keeps a snapshot of each version in Aiku, and the change is logged in ai-changes-tool, where it can be reverted. Only for group administrators.')]
class InfoWebsiteWriteTool extends AikuInfoWebsiteTool
{
    use WithMcpChangeLog;

    public function handle(Request $request): Response
    {
        $request->validate([
            'change_id'    => ['required', 'string'],
            'request_text' => ['required', 'string', 'max:4000'],
        ]);

        $denied = $this->denied($request);
        if ($denied) {
            return $denied;
        }

        $user   = $request->user();
        $staged = StageStaticWebsiteChange::find((string) $request->string('change_id'));
        if (!$staged || $staged['user_id'] !== $user->id || $staged['group_id'] !== $user->group_id) {
            return Response::error('There is no change '.$request->string('change_id').' prepared by this user, or it expired. Prepare it again with info-website-preview-tool. Nothing was changed.');
        }

        $staticWebsite = StaticWebsite::find($staged['static_website_id']);
        if (!$staticWebsite) {
            return Response::error('That info website no longer exists. Nothing was changed.');
        }

        $paths = array_keys($staged['pages']);
        $codes = array_keys($staged['components']);

        try {
            $this->recordChange(
                $request,
                McpChangeTypeEnum::INFO_WEBSITE_CONTENT,
                $staticWebsite->domain.': '.$staged['summary'],
                ['static_website_id' => $staticWebsite->id, 'pages' => $paths, 'components' => $codes],
                fn () => ApplyStaticWebsiteChange::run($staticWebsite, $staged, 'AI change for '.$user->username.': '.$request->string('request_text')),
                [
                    'domain'              => $staticWebsite->domain,
                    'operation'           => $staged['operation'],
                    'before_snapshot_ids' => ApplyStaticWebsiteChange::make()->snapshotsBeforeChange($staticWebsite, $paths, $codes),
                ]
            );
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()));
        }

        StageStaticWebsiteChange::forget($staged['id']);

        return Response::json([
            'saved'         => true,
            'website'       => $staticWebsite->domain,
            'summary'       => $staged['summary'],
            'pages'         => collect($staged['affected_pages'])->map(fn (string $path) => [
                'path'        => $path,
                'deleted'     => array_key_exists($path, $staged['pages']) && $staged['pages'][$path] === null,
                'live_url'    => $this->liveUrl($staticWebsite, $path),
                'preview_url' => $this->previewUrl($staticWebsite, $path),
            ])->all(),
            'change_log_id' => $this->mcpChange?->id,
            'note'          => 'Live pages can take up to 5 minutes to show the change. To undo it, use ai-changes-tool with this change_log_id after the user confirmed.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'change_id'    => $schema->string()->description('change_id returned by info-website-preview-tool')->required(),
            'request_text' => $schema->string()->description('The user\'s confirmation, verbatim, kept in the change log')->required(),
        ];
    }
}
