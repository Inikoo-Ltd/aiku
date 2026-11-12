<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Web\StaticWebsite\StageStaticWebsiteChange;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Prepares a change to an info website and shows it, without saving anything. operation: update_page (edits and/or title, description), create_page (path, title, description, from_page template defaulting to /, then content_html to fill its content area and/or edits), delete_page, update_component (edits to a shared part: header, footer or one added), create_component (component code, html, pages "all" or a list of paths, position after_header or before_footer), delete_component (also takes it off every page). Edits quote HTML exactly as info-websites-tool find shows it: {"action": "replace"|"insert_before"|"insert_after"|"remove", "find": "...", "html": "...", "all": false}; a quote that is missing, or there more than once without all=true, refuses the change. No scripts, inline event handlers or links to the old host. Returns a change_id, a summary, every edit as before/after text, the pages affected, warnings and a preview_url that shows the result in Aiku. Show all of that to the user, ask them to look at the preview and to confirm, and only then call info-website-write-tool with the change_id and their confirmation in their own words. Only for group administrators.')]
#[IsReadOnly]
class InfoWebsitePreviewTool extends AikuInfoWebsiteTool
{
    public function handle(Request $request): Response
    {
        $request->validate([
            'website'      => ['required', 'string'],
            'operation'    => ['required', 'string'],
            'page'         => ['sometimes', 'nullable', 'string'],
            'component'    => ['sometimes', 'nullable', 'string'],
            'edits'        => ['sometimes', 'array', 'max:50'],
            'title'        => ['sometimes', 'nullable', 'string'],
            'description'  => ['sometimes', 'nullable', 'string'],
            'path'         => ['sometimes', 'nullable', 'string'],
            'from_page'    => ['sometimes', 'nullable', 'string'],
            'content_html' => ['sometimes', 'nullable', 'string', 'max:500000'],
            'html'         => ['sometimes', 'nullable', 'string', 'max:500000'],
            'pages'        => ['sometimes'],
            'position'     => ['sometimes', 'nullable', 'string'],
        ]);

        $denied = $this->denied($request);
        if ($denied) {
            return $denied;
        }

        $staticWebsite = $this->staticWebsite($request);
        if ($staticWebsite instanceof Response) {
            return $staticWebsite;
        }

        try {
            $staged = StageStaticWebsiteChange::run($request->user(), $staticWebsite, $request->all());
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()).' Nothing was changed.');
        }

        return Response::json([
            'change_id'      => $staged['id'],
            'website'        => $staticWebsite->domain,
            'summary'        => $staged['summary'],
            'edits'          => $staged['diffs'],
            'affected_pages' => $staged['affected_pages'],
            'warnings'       => $staged['warnings'],
            'preview_url'    => route('grp.marketing.info_websites.changes.preview', [$staticWebsite->slug, $staged['id'], 'path' => $staged['preview_path']]),
            'expires_at'     => $staged['expires_at'],
            'saved'          => false,
            'next'           => 'Show the user the summary, every edit, the warnings and the preview_url (it opens in Aiku once they are logged in). Ask them to confirm. Call info-website-write-tool with this change_id only after they said yes in their own words.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'website'      => $schema->string()->description('Domain or slug of the info website')->required(),
            'operation'    => $schema->string()->description('update_page, create_page, delete_page, update_component, create_component or delete_component')->required(),
            'page'         => $schema->string()->description('Path of the page to change or delete, e.g. /our-story'),
            'component'    => $schema->string()->description('Code of the shared part to change, create or delete, e.g. header'),
            'edits'        => $schema->array()->items($schema->object([
                'action' => $schema->string()->description('replace, insert_before, insert_after or remove')->required(),
                'find'   => $schema->string()->description('Exact HTML to change, quoted from info-websites-tool find')->required(),
                'html'   => $schema->string()->description('New HTML; not used by remove'),
                'all'    => $schema->boolean()->description('Change every place the quote appears instead of exactly one'),
            ]))->description('Edits applied in order'),
            'title'        => $schema->string()->description('New page title (also updates the browser title and link previews)'),
            'description'  => $schema->string()->description('New page description for search engines'),
            'path'         => $schema->string()->description('create_page: path of the new page, e.g. /new-collection'),
            'from_page'    => $schema->string()->description('create_page: path of the page to copy as the template, default /'),
            'content_html' => $schema->string()->description('create_page: HTML for the content area of the new page, between the shared header and footer'),
            'html'         => $schema->string()->description('create_component: HTML of the new shared part'),
            'pages'        => $schema->array()->items($schema->string())->description('create_component: paths of the pages showing it; omit for all pages'),
            'position'     => $schema->string()->description('create_component: after_header (default) or before_footer'),
        ];
    }
}
