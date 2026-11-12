<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Web\StaticWebsite\WithStaticWebsiteEditing;
use App\Models\Web\StaticWebpage;
use App\Models\Web\StaticWebsite;
use App\Models\Web\StaticWebsiteComponent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Reads the info websites (information-only sites without a shop, e.g. aw-freedom.com) so they can be changed with info-website-preview-tool. Without arguments lists them; with website lists its pages (path, title, size, shared parts used) and its shared parts (header, footer and any added, with the pages using each); with website and page (a path like /our-story) or component (a shared part code like header) gives the title, description, live and preview links and an outline of the visible text. Add find to get every place a text appears with the exact HTML around it: quote that HTML exactly in edits. Add from and length to read a slice of the raw HTML. Pages hold <!--aiku:component:code--> where a shared part goes; change the part itself to change it on every page. Read only. Only for group administrators.')]
#[IsReadOnly]
class InfoWebsitesTool extends AikuInfoWebsiteTool
{
    use WithStaticWebsiteEditing;

    public function handle(Request $request): Response
    {
        $request->validate([
            'website'   => ['sometimes', 'string'],
            'page'      => ['sometimes', 'string'],
            'component' => ['sometimes', 'string'],
            'find'      => ['sometimes', 'string', 'max:2000'],
            'from'      => ['sometimes', 'integer', 'min:0'],
            'length'    => ['sometimes', 'integer', 'min:1', 'max:20000'],
        ]);

        $denied = $this->denied($request);
        if ($denied) {
            return $denied;
        }

        if (!$request->has('website')) {
            return Response::json(['info_websites' => $this->websites($request)]);
        }

        $staticWebsite = $this->staticWebsite($request);
        if ($staticWebsite instanceof Response) {
            return $staticWebsite;
        }

        if ($request->has('page')) {
            $path    = '/'.trim(strtok((string) $request->string('page'), '?#') ?: '', '/');
            $webpage = $staticWebsite->webpages()->where('path', $path)->first();
            if (!$webpage) {
                return Response::error("$staticWebsite->domain has no page $path. Its pages: ".$staticWebsite->webpages()->orderBy('path')->pluck('path')->implode(', ').'.');
            }

            return Response::json($this->page($request, $staticWebsite, $webpage));
        }

        if ($request->has('component')) {
            $component = $staticWebsite->components()->where('code', strtolower((string) $request->string('component')))->first();
            if (!$component) {
                return Response::error("$staticWebsite->domain has no shared part '{$request->string('component')}'. Its shared parts: ".$staticWebsite->components()->orderBy('code')->pluck('code')->implode(', ').'.');
            }

            return Response::json($this->component($request, $staticWebsite, $component));
        }

        return Response::json($this->overview($staticWebsite));
    }

    private function websites(Request $request): array
    {
        return StaticWebsite::where('group_id', $request->user()->group_id)->orderBy('domain')->get()
            ->map(fn (StaticWebsite $staticWebsite) => [
                'domain'        => $staticWebsite->domain,
                'name'          => $staticWebsite->name,
                'state'         => $staticWebsite->state->value,
                'pages'         => $staticWebsite->webpages()->count(),
                'shared_parts'  => $staticWebsite->components()->orderBy('code')->pluck('code')->all(),
                'live_url'      => $this->liveUrl($staticWebsite),
                'preview_url'   => $this->previewUrl($staticWebsite),
            ])->all();
    }

    private function overview(StaticWebsite $staticWebsite): array
    {
        return [
            'domain'       => $staticWebsite->domain,
            'name'         => $staticWebsite->name,
            'pages'        => $staticWebsite->webpages()->orderBy('path')->get(['path', 'title', 'html'])
                ->map(fn (StaticWebpage $webpage) => [
                    'path'         => $webpage->path,
                    'title'        => $webpage->title,
                    'size_kb'      => $this->kilobytes((string) $webpage->html),
                    'shared_parts' => StaticWebsiteComponent::codesIn((string) $webpage->html),
                ])->all(),
            'shared_parts' => $staticWebsite->components()->orderBy('code')->get(['code', 'html'])
                ->map(fn (StaticWebsiteComponent $component) => [
                    'code'          => $component->code,
                    'size_kb'       => $this->kilobytes($component->html),
                    'used_by_pages' => $staticWebsite->webpages()->whereRaw('position(? in html) > 0', [StaticWebsiteComponent::marker($component->code)])->count(),
                ])->all(),
        ];
    }

    private function page(Request $request, StaticWebsite $staticWebsite, StaticWebpage $webpage): array
    {
        $html = (string) $webpage->html;

        return array_merge([
            'domain'       => $staticWebsite->domain,
            'path'         => $webpage->path,
            'title'        => $webpage->title,
            'description'  => $webpage->description,
            'shared_parts' => StaticWebsiteComponent::codesIn($html),
            'live_url'     => $this->liveUrl($staticWebsite, $webpage->path),
            'preview_url'  => $this->previewUrl($staticWebsite, $webpage->path),
            'html_length'  => strlen($html),
        ], $this->reading($request, $html));
    }

    private function component(Request $request, StaticWebsite $staticWebsite, StaticWebsiteComponent $component): array
    {
        return array_merge([
            'domain'        => $staticWebsite->domain,
            'component'     => $component->code,
            'used_by_pages' => $staticWebsite->webpages()->whereRaw('position(? in html) > 0', [StaticWebsiteComponent::marker($component->code)])->orderBy('path')->pluck('path')->all(),
            'html_length'   => strlen($component->html),
        ], $this->reading($request, $component->html));
    }

    private function reading(Request $request, string $html): array
    {
        if ($request->has('find')) {
            $find  = (string) $request->get('find');
            $found = $this->occurrences($html, $find);

            return [
                'find'        => $find,
                'occurrences' => substr_count($html, $find),
                'matches'     => $found,
                'hint'        => $found ? 'Quote the exact HTML you change from these matches in the edits.' : 'Not found: try a shorter text, or read the outline.',
            ];
        }

        if ($request->has('from') || $request->has('length')) {
            $from = $request->integer('from', 0);

            return ['from' => $from, 'html' => substr($html, $from, $request->integer('length', 8000))];
        }

        return [
            'outline' => $this->outline($html),
            'hint'    => 'Use find with a distinctive text to get the exact HTML to quote in edits.',
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'website'   => $schema->string()->description('Domain (e.g. aw-freedom.com) or slug of the info website'),
            'page'      => $schema->string()->description('Page path, e.g. / or /our-story'),
            'component' => $schema->string()->description('Shared part code, e.g. header or footer'),
            'find'      => $schema->string()->description('Text or HTML to look for in that page or shared part'),
            'from'      => $schema->integer()->description('Start of a raw HTML slice, in characters'),
            'length'    => $schema->integer()->description('Length of the raw HTML slice, up to 20000'),
        ];
    }
}
