<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Docs;

use App\Actions\UI\AikuPublic\BlogPosts;
use App\Models\Web\Website;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

class ShowIrisDoc
{
    use AsAction;
    use WithIrisDocs;

    /**
     * @return array<string, mixed>
     */
    public function handle(Website $website, string $slug): array
    {
        $this->ensureDropshippingWebsite($website);

        $doc = BlogPosts::find($slug, BlogPosts::DROPSHIPPING_DOCS);
        abort_unless($doc && $this->isForWebsite($doc, $website), 404);

        $everything   = $this->everything();
        $language     = $doc['lang'] === 'en' ? $this->language($website) : $doc['lang'];
        $translations = $everything->where('base_slug', $doc['base_slug'])->sortBy(fn (array $other) => $other['lang'] === 'en' ? '' : $other['lang'])->values();
        $english      = $translations->firstWhere('lang', 'en');

        return [
            'doc'          => array_merge($this->summary($doc, $website), [
                'html'            => $this->fillPlaceholders($this->localiseLinks($doc['html'], $language, $everything), $website, isHtml: true),
                'reading_minutes' => $doc['reading_minutes'],
                'notice'          => $doc['lang'] !== 'en' ? BlogPosts::LANGUAGES[$doc['lang']]['notice'] ?? null : null,
                'is_stale'        => $doc['lang'] !== 'en' && $english && (!$doc['source_date'] || $english['date']->gt($doc['source_date'])),
                'stale'           => $doc['lang'] !== 'en' ? BlogPosts::LANGUAGES[$doc['lang']]['stale'] ?? null : null,
            ]),
            'translations' => $translations->count() > 1
                ? $translations->map(fn (array $translation) => [
                    'lang' => $translation['lang'],
                    'name' => $translation['lang'] === 'en' ? 'English' : BlogPosts::LANGUAGES[$translation['lang']]['name'],
                    'url'  => '/'.self::PATH.'/'.$translation['slug'],
                ])->all()
                : [],
            'series'       => $this->series($doc, $language, $everything, $website),
        ];
    }

    /**
     * @param  array{series:?string}  $doc
     * @return array<int, array<string, mixed>>
     */
    private function series(array $doc, string $language, Collection $everything, Website $website): array
    {
        if (!$doc['series']) {
            return [];
        }

        return $everything
            ->where('lang', 'en')
            ->where('series', $doc['series'])
            ->filter(fn (array $english) => $this->isForWebsite($english, $website))
            ->sortBy('series_order')
            ->map(fn (array $english) => $this->summary($this->inLanguage($english, $language, $everything), $website))
            ->values()
            ->all();
    }

    private function localiseLinks(string $html, string $language, Collection $everything): string
    {
        return preg_replace_callback(
            '#href="/'.self::PATH.'/([a-z0-9-]+)"#',
            function (array $matches) use ($language, $everything) {
                $english = $everything->first(fn (array $doc) => $doc['slug'] === $matches[1] && $doc['lang'] === 'en');

                return $english ? 'href="/'.self::PATH.'/'.$this->inLanguage($english, $language, $everything)['slug'].'"' : $matches[0];
            },
            $html
        );
    }

    public function asController(string $slug, ActionRequest $request): Response
    {
        /** @var Website $website */
        $website = $request->input('website');

        $data = $this->handle($website, $slug);

        $response = Inertia::render('Docs/DocShow', $data)->withViewData([
            'browserTitle' => $data['doc']['title'].' | '.$website->name,
        ])->toResponse($request);

        return $this->cacheable($response, $website);
    }
}
