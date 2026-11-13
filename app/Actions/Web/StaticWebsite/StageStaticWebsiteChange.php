<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite;

use App\Enums\Web\StaticWebsite\StaticWebsiteComponentEnum;
use App\Models\SysAdmin\User;
use App\Models\Web\StaticWebpage;
use App\Models\Web\StaticWebsite;
use App\Models\Web\StaticWebsiteComponent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Works out the whole result of a change to an info website without saving anything: every page and
 * shared part it touches, as they would be. The result is kept for a day under a change id, tied to the
 * user and to the content it was worked out from, so it can be previewed and then applied as shown.
 */
class StageStaticWebsiteChange
{
    use AsAction;
    use WithStaticWebsiteEditing;
    use WithStaticWebsiteHtml;

    public const array OPERATIONS = ['update_page', 'create_page', 'delete_page', 'update_component', 'create_component', 'delete_component'];

    public const string CACHE_PREFIX = 'static_website_change:';

    /**
     * @return array<string, mixed> the staged change, including its preview
     */
    public function handle(User $user, StaticWebsite $staticWebsite, array $input): array
    {
        $operation = (string) ($input['operation'] ?? '');
        if (!in_array($operation, self::OPERATIONS, true)) {
            $this->refuse('operation must be one of '.implode(', ', self::OPERATIONS).'.');
        }

        $staged = match ($operation) {
            'update_page'      => $this->updatePage($staticWebsite, $input),
            'create_page'      => $this->createPage($staticWebsite, $input),
            'delete_page'      => $this->deletePage($staticWebsite, $input),
            'update_component' => $this->updateComponent($staticWebsite, $input),
            'create_component' => $this->createComponent($staticWebsite, $input),
            'delete_component' => $this->deleteComponent($staticWebsite, $input),
        };

        $id     = (string) Str::uuid();
        $staged = array_merge($staged, [
            'id'                => $id,
            'operation'         => $operation,
            'user_id'           => $user->id,
            'group_id'          => $staticWebsite->group_id,
            'static_website_id' => $staticWebsite->id,
            'domain'            => $staticWebsite->domain,
            'base'              => GetStaticWebsiteContentState::run($staticWebsite, array_keys($staged['pages']), array_keys($staged['components'])),
            'expires_at'        => now()->addDay()->toIso8601String(),
        ]);

        Cache::put(self::CACHE_PREFIX.$id, $staged, now()->addDay());

        return $staged;
    }

    public static function find(string $id): ?array
    {
        return Str::isUuid($id) ? Cache::get(self::CACHE_PREFIX.$id) : null;
    }

    public static function forget(string $id): void
    {
        Cache::forget(self::CACHE_PREFIX.$id);
    }

    private function updatePage(StaticWebsite $staticWebsite, array $input): array
    {
        $webpage = $this->existingPage($staticWebsite, $input['page'] ?? null);
        $edits   = $input['edits'] ?? [];
        $title   = $this->optionalText($input, 'title', 255);
        $text    = $this->optionalText($input, 'description', 1000);

        if (!$edits && $title === null && $text === null) {
            $this->refuse('Nothing to change: pass edits, a title or a description.');
        }

        [$html, $diffs] = $this->applyEdits($staticWebsite, (string) $webpage->html, $edits, $webpage->path);
        $html           = $this->withHeadMeta($html, $title, $text, null);

        return $this->stagedResult(
            summary: "Change page $webpage->path".($diffs ? ': '.count($diffs).' edit'.(count($diffs) === 1 ? '' : 's') : '').($title !== null ? ', new title' : '').($text !== null ? ', new description' : ''),
            pages: [$webpage->path => ['title' => $title ?? $webpage->title, 'description' => $text ?? $webpage->description, 'html' => $html]],
            diffs: $diffs,
            previewPath: $webpage->path,
        );
    }

    private function createPage(StaticWebsite $staticWebsite, array $input): array
    {
        $path = $this->normalisePath((string) ($input['path'] ?? ''));
        if (!preg_match('#^/[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)*$#', $path)) {
            $this->refuse('path must look like /our-story or /blog/new-post: lowercase letters, digits and dashes.');
        }
        if ($staticWebsite->webpages()->where('path', $path)->exists()) {
            $this->refuse("$path already exists; change it with update_page instead.");
        }
        $title = $this->optionalText($input, 'title', 255) ?? $this->refuse('A new page needs a title.');
        $text  = $this->optionalText($input, 'description', 1000);

        $template = $this->existingPage($staticWebsite, $input['from_page'] ?? '/');
        $html     = (string) $template->html;

        if (isset($input['content_html'])) {
            $content = (string) $input['content_html'];
            $this->checkNewHtml($staticWebsite, $content, 'content_html');
            $element = $this->findElement($html, 'article', 'id="sections"') ?? $this->findElement($html, 'main', 'id="page"');
            if (!$element) {
                $this->refuse("The page $template->path has no content area to fill; use edits on it instead.");
            }
            $html = substr_replace($html, $content, $element[1], $element[2] - $element[1]);
        }

        [$html, $diffs] = $this->applyEdits($staticWebsite, $html, $input['edits'] ?? [], $path);
        $html           = $this->withHeadMeta($html, $title, $text ?? '', 'https://www.'.$staticWebsite->domain.$path);

        return $this->stagedResult(
            summary: "Create page $path (\"$title\") from $template->path",
            pages: [$path => ['title' => $title, 'description' => $text, 'html' => $html]],
            diffs: $diffs,
            previewPath: $path,
        );
    }

    private function deletePage(StaticWebsite $staticWebsite, array $input): array
    {
        $webpage = $this->existingPage($staticWebsite, $input['page'] ?? null);
        if ($webpage->path === '/') {
            $this->refuse('The home page cannot be deleted.');
        }

        $linkedFrom = $staticWebsite->webpages()->where('id', '!=', $webpage->id)
            ->whereRaw('html ~ ?', ['href="'.preg_quote($webpage->path, '/').'(["?#])'])
            ->orderBy('path')->pluck('path')->all();
        $sharedLinks = $staticWebsite->components()
            ->whereRaw('html ~ ?', ['href="'.preg_quote($webpage->path, '/').'(["?#])'])
            ->pluck('code')->all();

        return $this->stagedResult(
            summary: "Delete page $webpage->path (\"".($webpage->title ?: 'no title').'")',
            pages: [$webpage->path => null],
            previewPath: $webpage->path,
            warnings: array_values(array_filter([
                $linkedFrom ? 'These pages still link to it and will show a not found page there: '.implode(', ', $linkedFrom) : null,
                $sharedLinks ? 'The shared '.implode(', ', $sharedLinks).' links to it on every page; change the link there too.' : null,
            ])),
        );
    }

    private function updateComponent(StaticWebsite $staticWebsite, array $input): array
    {
        $component = $this->existingComponent($staticWebsite, $input['component'] ?? null);
        if (!($input['edits'] ?? [])) {
            $this->refuse('Nothing to change: pass edits.');
        }

        [$html, $diffs] = $this->applyEdits($staticWebsite, $component->html, $input['edits'], 'shared '.$component->code);
        $usedBy         = $this->pagesUsing($staticWebsite, $component->code);

        return $this->stagedResult(
            summary: 'Change the shared '.$component->code.' on '.count($usedBy).' page'.(count($usedBy) === 1 ? '' : 's').': '.count($diffs).' edit'.(count($diffs) === 1 ? '' : 's'),
            components: [$component->code => $html],
            diffs: $diffs,
            previewPath: $usedBy[0] ?? '/',
            affectedPages: $usedBy,
        );
    }

    private function createComponent(StaticWebsite $staticWebsite, array $input): array
    {
        $code = Str::lower(trim((string) ($input['component'] ?? '')));
        if (!preg_match('/^'.StaticWebsiteComponent::CODE_PATTERN.'$/', $code)) {
            $this->refuse('component must be a short code like announcement or newsletter-signup: lowercase letters, digits and dashes.');
        }
        if ($staticWebsite->components()->where('code', $code)->exists()) {
            $this->refuse("The shared $code already exists; change it with update_component instead.");
        }
        $html = (string) ($input['html'] ?? '');
        if (trim($html) === '') {
            $this->refuse('A new shared part needs its html.');
        }
        $this->checkNewHtml($staticWebsite, $html, "shared $code");

        $position = $input['position'] ?? 'after_header';
        if (!in_array($position, ['after_header', 'before_footer'], true)) {
            $this->refuse('position must be after_header or before_footer.');
        }
        $requested = $input['pages'] ?? 'all';
        $webpages  = $requested === 'all'
            ? $staticWebsite->webpages()->whereNotNull('html')->orderBy('path')->get()
            : collect((array) $requested)->map(fn ($path) => $this->existingPage($staticWebsite, $path));

        $anchor = $position === 'after_header' ? StaticWebsiteComponentEnum::HEADER : StaticWebsiteComponentEnum::FOOTER;
        $pages  = [];
        foreach ($webpages as $webpage) {
            $element = $this->findComponentElement((string) $webpage->html, $anchor);
            if (!$element) {
                $this->refuse("$webpage->path has no ".$anchor->value.' to place the new part next to.');
            }
            $closeEnd = strpos((string) $webpage->html, '>', $element[2]) + 1;
            $at       = $position === 'after_header' ? $closeEnd : $element[0];

            $pages[$webpage->path] = [
                'title'       => $webpage->title,
                'description' => $webpage->description,
                'html'        => substr_replace((string) $webpage->html, StaticWebsiteComponent::marker($code)."\n", $at, 0),
            ];
        }

        return $this->stagedResult(
            summary: "Create the shared $code and show it ".str_replace('_', ' ', $position).' on '.count($pages).' page'.(count($pages) === 1 ? '' : 's'),
            pages: $pages,
            components: [$code => $html],
            diffs: [['action' => 'create', 'before' => '', 'after' => $this->readable($html), 'occurrences' => count($pages)]],
            previewPath: array_key_first($pages) ?? '/',
        );
    }

    private function deleteComponent(StaticWebsite $staticWebsite, array $input): array
    {
        $component = $this->existingComponent($staticWebsite, $input['component'] ?? null);
        $marker    = StaticWebsiteComponent::marker($component->code);
        $usedBy    = $staticWebsite->webpages()->whereRaw('position(? in html) > 0', [$marker])->orderBy('path')->get();

        $pages = [];
        foreach ($usedBy as $webpage) {
            $pages[$webpage->path] = [
                'title'       => $webpage->title,
                'description' => $webpage->description,
                'html'        => str_replace([$marker."\n", $marker], '', (string) $webpage->html),
            ];
        }

        return $this->stagedResult(
            summary: 'Delete the shared '.$component->code.' and take it off '.count($pages).' page'.(count($pages) === 1 ? '' : 's'),
            pages: $pages,
            components: [$component->code => null],
            previewPath: array_key_first($pages) ?? '/',
            warnings: in_array($component->code, ['header', 'footer'], true)
                ? ['Every page using it loses its '.$component->code.(($component->code === 'header') ? ' and menu' : '').'.']
                : [],
        );
    }

    /**
     * @param array<string, array{title: ?string, description: ?string, html: string}|null> $pages
     * @param array<string, string|null> $components
     * @param list<array> $diffs
     * @param list<string> $warnings
     * @param list<string> $affectedPages pages that change only through a shared part
     */
    private function stagedResult(string $summary, array $pages = [], array $components = [], array $diffs = [], string $previewPath = '/', array $warnings = [], array $affectedPages = []): array
    {
        return [
            'summary'        => $summary,
            'pages'          => $pages,
            'components'     => $components,
            'diffs'          => $diffs,
            'warnings'       => $warnings,
            'affected_pages' => array_values(array_unique(array_merge(array_keys($pages), $affectedPages))),
            'preview_path'   => $previewPath,
        ];
    }

    private function existingPage(StaticWebsite $staticWebsite, mixed $path): StaticWebpage
    {
        $path    = $this->normalisePath((string) $path);
        $webpage = $staticWebsite->webpages()->where('path', $path)->first();
        if (!$webpage) {
            $paths = $staticWebsite->webpages()->orderBy('path')->pluck('path')->implode(', ');
            $this->refuse("$staticWebsite->domain has no page $path. Its pages: $paths.");
        }

        return $webpage;
    }

    private function existingComponent(StaticWebsite $staticWebsite, mixed $code): StaticWebsiteComponent
    {
        $component = $staticWebsite->components()->where('code', Str::lower(trim((string) $code)))->first();
        if (!$component) {
            $codes = $staticWebsite->components()->orderBy('code')->pluck('code')->implode(', ');
            $this->refuse("$staticWebsite->domain has no shared part '$code'. Its shared parts: $codes.");
        }

        return $component;
    }

    /**
     * @return list<string>
     */
    private function pagesUsing(StaticWebsite $staticWebsite, string $code): array
    {
        return $staticWebsite->webpages()->whereRaw('position(? in html) > 0', [StaticWebsiteComponent::marker($code)])->orderBy('path')->pluck('path')->all();
    }

    private function normalisePath(string $path): string
    {
        $path = trim($path);
        if (str_contains($path, '://')) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }

        return '/'.trim(strtok($path, '?#') ?: '', '/');
    }

    private function optionalText(array $input, string $key, int $max): ?string
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            return null;
        }
        $value = trim((string) $input[$key]);
        if (mb_strlen($value) > $max) {
            $this->refuse("$key is longer than $max characters.");
        }

        return $value;
    }
}
